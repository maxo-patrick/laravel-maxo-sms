<?php

use Illuminate\Support\Facades\Http;
use MaxoTel\Sms\Contracts\SmsSender;
use MaxoTel\Sms\Data\SmsResult;
use MaxoTel\Sms\Data\SmsResultCollection;
use MaxoTel\Sms\Enums\SmsErrorType;
use MaxoTel\Sms\Enums\SmsStatus;
use MaxoTel\Sms\Exceptions\ConfigurationException;
use MaxoTel\Sms\Exceptions\SmsSendException;
use MaxoTel\Sms\Facades\Sms;
use MaxoTel\Sms\SmsManager;
use MaxoTel\Sms\Support\AustralianMobile;
use MaxoTel\Sms\Support\MessageEncoding;

it('resolves the sender contract from the container', function () {
    expect(app(SmsSender::class))->toBeInstanceOf(SmsManager::class);
});

it('normalizes australian numbers to e164 and local format', function () {
    expect(AustralianMobile::toE164('0412345678'))->toBe('+61412345678')
        ->and(AustralianMobile::toE164('+61412345678'))->toBe('+61412345678')
        ->and(AustralianMobile::toE164('61412345678'))->toBe('+61412345678')
        ->and(AustralianMobile::toE164('0412 345 678'))->toBe('+61412345678')
        ->and(AustralianMobile::toE164('0212345678'))->toBeNull()
        ->and(AustralianMobile::toLocal04('+61412345678'))->toBe('0412345678');
});

it('counts gsm-7 and unicode segments', function () {
    expect(MessageEncoding::segments(str_repeat('A', 160)))->toBe(1)
        ->and(MessageEncoding::segments(str_repeat('A', 161)))->toBe(2)
        ->and(MessageEncoding::isGsm7('Hello'))->toBeTrue()
        ->and(MessageEncoding::isGsm7('你好'))->toBeFalse()
        ->and(MessageEncoding::segments('你好'))->toBe(1);
});

it('sends a successful sms through the maxo api', function () {
    Http::fake([
        'https://myapi.maxo.com.au/sms/send' => Http::response([
            'status' => 1,
            'request' => 'sms/send',
            'smsid' => 1980554,
            'parts' => 1,
            'price' => 0.1,
        ], 200),
    ]);

    $result = Sms::to('0412345678')->message('Hello from Laravel')->send();

    expect($result)->toBeInstanceOf(SmsResult::class)
        ->and($result->successful)->toBeTrue()
        ->and($result->status)->toBe(SmsStatus::Sent)
        ->and($result->smsId)->toBe(1980554)
        ->and($result->parts)->toBe(1)
        ->and($result->price)->toBe(0.1)
        ->and($result->recipient)->toBe('+61412345678');

    Http::assertSent(function ($request): bool {
        $data = $request->data();

        return $request->url() === 'https://myapi.maxo.com.au/sms/send'
            && $request->method() === 'POST'
            && $request->isForm()
            && $data['destination'] === '0412345678'
            && $data['origin'] === '0411111111'
            && $data['message'] === 'Hello from Laravel'
            && $data['key'] === 'test-key';
    });
});

it('sends to an inbox when configured', function () {
    config(['sms.connections.maxo.smsinbox' => 10]);

    Http::fake([
        'https://myapi.maxo.com.au/sms/10/send' => Http::response([
            'status' => 1,
            'smsid' => 99,
            'parts' => 1,
            'price' => 0.1,
        ], 200),
    ]);

    $result = Sms::to('0412345678')->message('Inbox send')->send();

    expect($result->successful)->toBeTrue()->and($result->smsInbox)->toBe(10);

    Http::assertSent(fn ($request): bool => $request->isForm()
        && $request->url() === 'https://myapi.maxo.com.au/sms/10/send'
        && $request['key'] === 'test-key');
});

it('maps validation errors for international numbers', function () {
    Http::fake([
        'https://myapi.maxo.com.au/sms/send' => Http::response([
            'status' => 0,
            'request' => 'sms/send',
            'error' => 'VALIDATION',
            'reason' => 'Sending to International numbers is not supported',
        ], 200),
    ]);

    $result = Sms::to('0412345678')->message('Hello')->send();

    expect($result->successful)->toBeFalse()
        ->and($result->errorType)->toBe(SmsErrorType::InvalidRecipient)
        ->and($result->providerError)->toBe('VALIDATION');
});

it('returns a failed result for an invalid recipient without calling the api', function () {
    Http::fake();

    $result = Sms::to('0212345678')->message('Hello')->send();

    expect($result->successful)->toBeFalse()
        ->and($result->errorType)->toBe(SmsErrorType::InvalidRecipient);

    Http::assertNothingSent();
});

it('fans out multiple recipients and keeps valid sends going', function () {
    Http::fake([
        'https://myapi.maxo.com.au/sms/send' => Http::response([
            'status' => 1,
            'smsid' => 1,
            'parts' => 1,
            'price' => 0.1,
        ], 200),
    ]);

    $results = Sms::to(['0412345678', '0212345678'])->message('Hello')->send();

    expect($results)->toBeInstanceOf(SmsResultCollection::class)
        ->and($results)->toHaveCount(2)
        ->and($results->successful())->toHaveCount(1)
        ->and($results->failed())->toHaveCount(1)
        ->and($results->allSucceeded())->toBeFalse();
});

it('throws configuration errors for a missing sender', function () {
    config(['sms.from' => null, 'sms.connections.maxo.from' => null]);

    Sms::to('0412345678')->message('Hello')->send();
})->throws(ConfigurationException::class);

it('throws on demand from a failed result', function () {
    Http::fake([
        'https://myapi.maxo.com.au/sms/send' => Http::response([
            'status' => 0,
            'error' => 'VALIDATION',
            'reason' => 'Sending to International numbers is not supported',
        ], 200),
    ]);

    Sms::to('0412345678')->message('Hello')->send()->throw();
})->throws(SmsSendException::class);

it('classifies a 503 as provider unavailable', function () {
    Http::fake([
        'https://myapi.maxo.com.au/sms/send' => Http::response(['status' => 0], 503),
    ]);

    $result = Sms::to('0412345678')->message('Hello')->send();

    expect($result->errorType)->toBe(SmsErrorType::ProviderUnavailable)
        ->and($result->status)->toBe(SmsStatus::Failed);
});

it('classifies a 500 as indeterminate', function () {
    Http::fake([
        'https://myapi.maxo.com.au/sms/send' => Http::response('error', 500),
    ]);

    $result = Sms::to('0412345678')->message('Hello')->send();

    expect($result->errorType)->toBe(SmsErrorType::IndeterminateError)
        ->and($result->status)->toBe(SmsStatus::Failed);
});

it('uses pretend mode without contacting the api', function () {
    config(['sms.pretend' => true]);
    Http::fake();

    $result = Sms::to('0412345678')->message('Hello')->send();

    expect($result->successful)->toBeTrue();
    Http::assertNothingSent();
});
