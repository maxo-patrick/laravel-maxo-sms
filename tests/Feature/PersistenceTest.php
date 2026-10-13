<?php

use Illuminate\Support\Facades\Http;
use MaxoTel\Sms\Contracts\SmsHistory;
use MaxoTel\Sms\Data\SmsHistoryQuery;
use MaxoTel\Sms\Enums\SmsStatus;
use MaxoTel\Sms\Exceptions\HistoryUnavailableException;
use MaxoTel\Sms\Facades\Sms;
use MaxoTel\Sms\Models\SmsLog;

it('throws when history is queried without persistence', function () {
    app(SmsHistory::class)->find('missing');
})->throws(HistoryUnavailableException::class);

it('persists a send and exposes it through history', function () {
    config(['sms.logging.enabled' => true, 'sms.logging.store_body' => true]);
    migrateSmsLogs();

    Http::fake([
        'https://myapi.maxo.com.au/sms/send' => Http::response([
            'status' => 1,
            'smsid' => 42,
            'parts' => 1,
            'price' => 0.1,
        ], 200),
    ]);

    $result = Sms::to('0412345678')->message('Stored')->reference('order-1')->send();

    expect(SmsLog::query()->count())->toBe(1);

    $record = app(SmsHistory::class)->find($result->uuid);

    expect($record)->not->toBeNull()
        ->and($record->smsId)->toBe(42)
        ->and($record->status)->toBe(SmsStatus::Sent)
        ->and($record->body)->toBe('Stored')
        ->and(app(SmsHistory::class)->statusFor($result->uuid))->toBe(SmsStatus::Sent)
        ->and(app(SmsHistory::class)->search(new SmsHistoryQuery(reference: 'order-1'))->total())->toBe(1);
});

it('returns the original result for a repeated idempotency key', function () {
    config(['sms.logging.enabled' => true]);
    migrateSmsLogs();

    Http::fake([
        'https://myapi.maxo.com.au/sms/send' => Http::response([
            'status' => 1,
            'smsid' => 7,
            'parts' => 1,
            'price' => 0.1,
        ], 200),
    ]);

    $first = Sms::to('0412345678')->message('Hello')->idempotencyKey('order-1')->send();
    $second = Sms::to('0412345678')->message('Hello')->idempotencyKey('order-1')->send();

    expect($second->smsId)->toBe($first->smsId);
    Http::assertSentCount(1);
});
