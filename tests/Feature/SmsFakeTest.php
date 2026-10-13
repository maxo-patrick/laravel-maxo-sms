<?php

use Illuminate\Support\Facades\Event;
use MaxoTel\Sms\Enums\SmsErrorType;
use MaxoTel\Sms\Events\SmsFailed;
use MaxoTel\Sms\Events\SmsQueued;
use MaxoTel\Sms\Events\SmsSending;
use MaxoTel\Sms\Events\SmsSent;
use MaxoTel\Sms\Facades\Sms;

it('records sent messages and still dispatches events', function () {
    Event::fake();

    Sms::fake();

    Sms::to('0412345678')->message('Hello')->send();

    Sms::assertSentTo('0412345678');
    Sms::assertSentCount(1);
    Sms::assertNothingQueued();

    Event::assertDispatched(SmsSending::class);
    Event::assertDispatched(SmsSent::class);
});

it('can simulate failures', function () {
    Event::fake();

    Sms::fake()->shouldFail(SmsErrorType::InvalidRecipient);

    $result = Sms::to('0412345678')->message('Hello')->send();

    expect($result->successful)->toBeFalse()
        ->and($result->errorType)->toBe(SmsErrorType::InvalidRecipient);

    Event::assertDispatched(SmsFailed::class);
});

it('records queued messages separately from sent messages', function () {
    Event::fake();

    Sms::fake();

    Sms::to('0412345678')->message('Hello')->queue();

    Sms::assertQueued();
    Sms::assertNothingSent();
    Event::assertDispatched(SmsQueued::class);
});

it('rejects invalid numbers on the fake', function () {
    Sms::fake();

    $result = Sms::to('0212345678')->message('Hello')->send();

    expect($result->errorType)->toBe(SmsErrorType::InvalidRecipient);
    Sms::assertSentCount(1);
});
