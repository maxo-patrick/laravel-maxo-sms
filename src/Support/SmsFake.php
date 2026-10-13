<?php

namespace MaxoTel\Sms\Support;

use Closure;
use DateTimeInterface;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Collection;
use MaxoTel\Sms\Contracts\SmsGateway;
use MaxoTel\Sms\Data\SmsMessage;
use MaxoTel\Sms\Data\SmsResult;
use MaxoTel\Sms\Enums\SmsErrorType;
use MaxoTel\Sms\Events\SmsFailed;
use MaxoTel\Sms\Events\SmsQueued;
use MaxoTel\Sms\Events\SmsSending;
use MaxoTel\Sms\Events\SmsSent;
use MaxoTel\Sms\Jobs\SendSms;
use MaxoTel\Sms\PendingSms;
use PHPUnit\Framework\Assert as PHPUnit;

class SmsFake implements SmsGateway
{
    /**
     * @var array<int, array{message: SmsMessage, result: SmsResult}>
     */
    private array $sent = [];

    /**
     * @var array<int, SmsMessage>
     */
    private array $queued = [];

    private ?SmsErrorType $failWith = null;

    /**
     * @var array<string, SmsErrorType>
     */
    private array $failFor = [];

    /**
     * @var Closure(SmsMessage): SmsResult|null
     */
    private ?Closure $responder = null;

    public function __construct(private Dispatcher $events) {}

    /**
     * @param  string|array<int, string>  $recipients
     */
    public function to(string|array $recipients): PendingSms
    {
        return (new PendingSms($this))->to($recipients);
    }

    public function message(string $body): PendingSms
    {
        return (new PendingSms($this))->message($body);
    }

    public function connection(?string $name = null): PendingSms
    {
        return new PendingSms($this);
    }

    public function currentConnection(): string
    {
        return (string) config('sms.default', 'maxo');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $this->events->dispatch(new SmsSending($message, $message->connection));

        $result = $this->resultFor($message);
        $this->sent[] = ['message' => $message, 'result' => $result];

        if ($result->successful) {
            $this->events->dispatch(new SmsSent($message, $result));
        } else {
            $this->events->dispatch(new SmsFailed($message, $result));
        }

        return $result;
    }

    /**
     * @param  array<int, SmsMessage>  $messages
     */
    public function queueMessages(array $messages, DateTimeInterface|int|null $delay = null): PendingDispatch|Batch
    {
        foreach ($messages as $message) {
            $this->queued[] = $message;
            $this->events->dispatch(new SmsQueued($message, $message->connection));
        }

        $pending = new FakePendingDispatch(new SendSms($messages[0], $messages[0]->connection));

        if ($delay !== null) {
            $pending->delay($delay);
        }

        return $pending;
    }

    public function shouldFail(SmsErrorType $type): static
    {
        $this->failWith = $type;

        return $this;
    }

    public function shouldFailFor(string $recipient, SmsErrorType $type): static
    {
        $e164 = AustralianMobile::toE164($recipient) ?? $recipient;
        $this->failFor[$e164] = $type;

        return $this;
    }

    /**
     * @param  Closure(SmsMessage): SmsResult  $callback
     */
    public function respondWith(Closure $callback): static
    {
        $this->responder = $callback;

        return $this;
    }

    public function assertSent(callable|string|null $callback = null): void
    {
        PHPUnit::assertTrue(
            $this->sentMatching($callback)->isNotEmpty(),
            'An unexpected SMS was not sent.'
        );
    }

    public function assertSentTo(string $recipient, ?callable $callback = null): void
    {
        $e164 = AustralianMobile::toE164($recipient) ?? $recipient;

        PHPUnit::assertTrue(
            $this->sentMatching($callback)->contains(fn (array $row): bool => $row['message']->recipient === $e164),
            "No SMS was sent to [{$recipient}]."
        );
    }

    public function assertNotSentTo(string $recipient): void
    {
        $e164 = AustralianMobile::toE164($recipient) ?? $recipient;

        PHPUnit::assertTrue(
            $this->sentMatching()->every(fn (array $row): bool => $row['message']->recipient !== $e164),
            "An SMS was unexpectedly sent to [{$recipient}]."
        );
    }

    public function assertNothingSent(): void
    {
        PHPUnit::assertCount(0, $this->sent, 'SMS messages were sent unexpectedly.');
    }

    public function assertSentCount(int $count): void
    {
        PHPUnit::assertCount($count, $this->sent, "Expected {$count} SMS messages to be sent.");
    }

    public function assertQueued(?callable $callback = null): void
    {
        $queued = Collection::make($this->queued);

        if ($callback) {
            $queued = $queued->filter($callback);
        }

        PHPUnit::assertTrue($queued->isNotEmpty(), 'An unexpected SMS was not queued.');
    }

    public function assertNothingQueued(): void
    {
        PHPUnit::assertCount(0, $this->queued, 'SMS messages were queued unexpectedly.');
    }

    /**
     * @return Collection<int, SmsMessage>
     */
    public function sent(): Collection
    {
        return Collection::make($this->sent)->map(fn (array $row): SmsMessage => $row['message'])->values();
    }

    private function resultFor(SmsMessage $message): SmsResult
    {
        if (! AustralianMobile::isValid($message->recipient)) {
            return SmsResult::failure($message, SmsErrorType::InvalidRecipient, errorMessage: 'Recipient is not a valid Australian mobile number.');
        }

        if ($this->responder) {
            return ($this->responder)($message);
        }

        if (isset($this->failFor[$message->recipient])) {
            return SmsResult::failure($message, $this->failFor[$message->recipient]);
        }

        if ($this->failWith !== null) {
            return SmsResult::failure($message, $this->failWith);
        }

        return SmsResult::accepted(
            message: $message,
            smsId: random_int(1, 999_999_999),
            parts: MessageEncoding::segments($message->body),
            price: 0.0,
        );
    }

    /**
     * @return Collection<int, array{message: SmsMessage, result: SmsResult}>
     */
    private function sentMatching(callable|string|null $callback = null): Collection
    {
        $sent = Collection::make($this->sent);

        if (is_string($callback)) {
            return $sent->filter(fn (array $row): bool => str_contains($row['message']->body, $callback));
        }

        if ($callback === null) {
            return $sent;
        }

        return $sent->filter(function (array $row) use ($callback): bool {
            return (bool) $callback($row['message'], $row['result']);
        });
    }
}
