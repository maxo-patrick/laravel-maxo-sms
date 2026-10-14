# MaxoTel Laravel SMS

Idiomatic Laravel access to the MaxoTel SMS API.

## Overview

This package wraps the MaxoTel SMS API in a Laravel-style interface: a `Sms` facade, a notification channel, queued jobs, events, and a test fake. It covers:

- **Sending** with a fluent builder: one or many recipients, sender, inbox, reference, metadata, and idempotency key.
- **Queueing** sends as jobs, so they run outside the web request.
- **Notifications** through the `sms` channel.
- **Inboxes**: list MaxoTel inboxes and read their messages.
- **History**: optional database log of sends, which you can search.
- **Events**: `SmsSending`, `SmsSent`, `SmsFailed`, and `SmsQueued`.
- **Named connections**, in the style of `config/mail.php`, for multiple API keys.
- **Testing** with `Sms::fake()` and assertions, plus a `pretend` mode that skips the API.

**Australian mobiles only.** MaxoTel accepts 10-digit `04` numbers. `0412345678`, `+61412345678` and `61412345678` are all accepted. International destinations are not supported.

## Requirements

PHP ^8.4 and Laravel 12.

## Installation

```bash
composer require maxotel/laravel-maxo-sms
```

Publish the config (and optionally the log table migration):

```bash
php artisan vendor:publish --tag=sms-config
php artisan vendor:publish --tag=sms-migrations
```

```env
SMS_CONNECTION=maxo
SMS_FROM=0411111111
MAXO_SMS_KEY=your-api-key
MAXO_SMS_FROM=0411111111
```

The API key must have the **SMS/Send Messages** permission. Inbox listing and retrieval also need **SMS/Get SMS Inbox**.

## Quickstart

### Send a message

```php
use MaxoTel\Sms\Facades\Sms;

$result = Sms::to('0412345678')
    ->message('Hello from Laravel')
    ->send();

if ($result->successful) {
    // accepted by MaxoTel — smsid, parts and price are on the result
}

$result->throw(); // optional: raise SmsSendException on failure
```

### List inboxes

Inject `SmsInboxes` and call `all()`. The default connection is used when you omit the connection name.

```php
use Illuminate\Http\JsonResponse;
use MaxoTel\Sms\Contracts\SmsInboxes;

public function listInboxes(SmsInboxes $inboxes): JsonResponse
{
    return response()->json($inboxes->all());
}
```

### Read messages from an inbox

Replace `1234` with an inbox ID from the list above. This reads messages from the last 30 days.

```php
use Illuminate\Http\JsonResponse;
use MaxoTel\Sms\Contracts\SmsInboxes;
use MaxoTel\Sms\Data\InboxMessageQuery;

public function inboxMessages(SmsInboxes $inboxes): JsonResponse
{
    $inboxId = 1234; // replace with your inbox ID

    $messages = $inboxes->messages(
        $inboxId,
        new InboxMessageQuery(
            startTime: now()->subDays(30),
            endTime: now(),
        ),
    );

    return response()->json($messages);
}
```

## Documentation

Full documentation is available at (#)

## License

MIT. See [LICENSE](LICENSE).