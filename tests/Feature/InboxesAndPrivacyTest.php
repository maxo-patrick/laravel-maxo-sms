<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use MaxoTel\Sms\Contracts\SmsInboxes;
use MaxoTel\Sms\Data\InboxMessageQuery;
use MaxoTel\Sms\Enums\MessageDirection;
use MaxoTel\Sms\Enums\SmsErrorType;
use MaxoTel\Sms\Exceptions\SmsInboxException;
use MaxoTel\Sms\Facades\Sms;
use MaxoTel\Sms\Support\ResponseSanitizer;

it('lists inboxes from the api', function () {
    Http::fake([
        'https://myapi.maxo.com.au/sms/list' => Http::response([
            'status' => 1,
            'count' => 2,
            'smsinboxes' => [
                ['smsinbox' => 10, 'name' => 'Customer SMS', 'senderid' => '0480011223'],
                ['smsinbox' => 11, 'name' => 'Staff SMS', 'senderid' => '0483322110'],
            ],
        ], 200),
    ]);

    $inboxes = app(SmsInboxes::class)->all();

    expect($inboxes)->toHaveCount(2)
        ->and($inboxes->first()->id)->toBe(10)
        ->and($inboxes->first()->name)->toBe('Customer SMS');

    Http::assertSent(fn (Request $request): bool => $request->isForm()
        && $request->url() === 'https://myapi.maxo.com.au/sms/list'
        && $request['key'] === 'test-key');
});

it('retrieves inbox messages and keeps status as a raw string', function () {
    Http::fake([
        'https://myapi.maxo.com.au/sms/10/messages' => Http::response([
            'status' => 1,
            'messages' => [[
                'smsid' => 2864649,
                'smsinbox' => 10,
                'datetime' => '2023-08-22 10:56:49',
                'unixtime' => 1692665809,
                'contact' => '0499887766',
                'direction' => 'Receive',
                'origin' => '0499887766',
                'destination' => '0480011223',
                'status' => 'Delivered',
                'read' => 1,
                'cost' => '0',
                'message' => 'Test message',
            ]],
        ], 200),
    ]);

    $messages = app(SmsInboxes::class)->messages(10, new InboxMessageQuery('2023-06-01', '2024-05-20'));

    expect($messages)->toHaveCount(1)
        ->and($messages->first()->status)->toBe('Delivered')
        ->and($messages->first()->direction)->toBe(MessageDirection::Receive)
        ->and($messages->first()->contact)->toBe('+61499887766');

    Http::assertSent(fn (Request $request): bool => $request->isForm()
        && str_ends_with($request->url(), '/sms/10/messages')
        && $request['key'] === 'test-key');
});

it('throws when the inbox list is rejected', function () {
    Http::fake([
        'https://myapi.maxo.com.au/sms/list' => Http::response([
            'status' => 0,
            'error' => 'FORBIDDEN',
            'reason' => 'The supplied key has insufficient permissions for SMS/Get SMS Inbox.',
        ], 403),
    ]);

    expect(fn () => app(SmsInboxes::class)->all())
        ->toThrow(SmsInboxException::class, 'insufficient permissions');

    try {
        app(SmsInboxes::class)->all();
    } catch (SmsInboxException $exception) {
        expect($exception->errorType)->toBe(SmsErrorType::AuthenticationError)
            ->and($exception->httpStatus)->toBe(403)
            ->and($exception->providerError)->toBe('FORBIDDEN');
    }
});

it('throws when inbox messages cannot connect', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect to 127.0.0.1 port 9'));

    try {
        app(SmsInboxes::class)->messages(10, new InboxMessageQuery('2024-06-01', '2024-06-30'));
        $exception = null;
    } catch (SmsInboxException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(SmsInboxException::class)
        ->and($exception->errorType)->toBe(SmsErrorType::NetworkError)
        ->and($exception->httpStatus)->toBe(0);
});

it('downloads inbox messages as csv', function () {
    Http::fake([
        'https://myapi.maxo.com.au/sms/10/messages' => Http::response(
            "smsid,message\n1,Hello\n",
            200,
            ['Content-Type' => 'text/comma-separated-values'],
        ),
    ]);

    $csv = app(SmsInboxes::class)->messagesCsv(10, new InboxMessageQuery('2024-06-01', '2024-06-30'));

    expect($csv)->toBe("smsid,message\n1,Hello\n");

    Http::assertSent(function (Request $request): bool {
        $accept = implode(',', (array) $request->header('Accept'));

        return $request->isForm()
            && $request['outputFormat'] === 'csv'
            && (string) $request['showHeadings'] === '1'
            && ! str_contains($accept, 'application/json');
    });
});

it('omits csv headings when they are turned off and throws on rejection', function () {
    Http::fake([
        'https://myapi.maxo.com.au/sms/10/messages' => Http::sequence()
            ->push("smsid\n1\n", 200, ['Content-Type' => 'text/csv'])
            ->push([
                'status' => 0,
                'error' => 'FORBIDDEN',
                'reason' => 'Insufficient permissions.',
            ], 403, ['Content-Type' => 'application/json']),
    ]);

    app(SmsInboxes::class)->messagesCsv(10, new InboxMessageQuery('2024-06-01', '2024-06-30'), false);

    Http::assertSent(fn (Request $request): bool => $request['outputFormat'] === 'csv'
        && ! array_key_exists('showHeadings', $request->data()));

    expect(fn () => app(SmsInboxes::class)->messagesCsv(10, new InboxMessageQuery('2024-06-01', '2024-06-30')))
        ->toThrow(SmsInboxException::class);
});

it('redacts keys from provider responses', function () {
    $sanitizer = new ResponseSanitizer(['key', 'token']);

    expect($sanitizer->sanitize(['key' => 'secret', 'nested' => ['token' => 'abc'], 'ok' => 1]))
        ->toMatchArray(['key' => '[REDACTED]', 'nested' => ['token' => '[REDACTED]'], 'ok' => 1]);
});

it('never puts the api key in the request url', function () {
    Http::fake([
        'https://myapi.maxo.com.au/sms/send' => Http::response([
            'status' => 1,
            'smsid' => 1,
            'parts' => 1,
            'price' => 0.1,
        ], 200),
    ]);

    Sms::to('0412345678')->message('Hello')->send();

    Http::assertSent(fn (Request $request): bool => ! str_contains($request->url(), 'test-key'));
});
