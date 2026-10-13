<?php

namespace MaxoTel\Sms;

use DateTimeInterface;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use MaxoTel\Sms\Contracts\SmsGateway;
use MaxoTel\Sms\Data\SmsMessage;
use MaxoTel\Sms\Data\SmsResult;
use MaxoTel\Sms\Enums\SmsErrorType;
use MaxoTel\Sms\Enums\SmsStatus;
use MaxoTel\Sms\Events\SmsFailed;
use MaxoTel\Sms\Events\SmsQueued;
use MaxoTel\Sms\Events\SmsSending;
use MaxoTel\Sms\Events\SmsSent;
use MaxoTel\Sms\Jobs\SendSms;
use MaxoTel\Sms\Logging\SmsLogger;
use MaxoTel\Sms\Maxo\MaxoClient;
use MaxoTel\Sms\Support\AustralianMobile;
use MaxoTel\Sms\Support\MessageEncoding;

/**
 * @internal
 */
class SmsManager implements SmsGateway
{
    public function __construct(
        private MaxoClient $client,
        private SmsLogger $logger,
        private Dispatcher $events,
        private ?string $connection = null,
    ) {}

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
        $manager = $name === null ? $this : new self($this->client, $this->logger, $this->events, $name);

        return new PendingSms($manager);
    }

    public function currentConnection(): string
    {
        return $this->connection ?? (string) config('sms.default', 'maxo');
    }

    public function send(SmsMessage $message): SmsResult
    {
        if (! AustralianMobile::isValid($message->recipient) || AustralianMobile::toE164($message->recipient) === null) {
            $result = SmsResult::failure(
                message: $message,
                errorType: SmsErrorType::InvalidRecipient,
                errorMessage: 'Recipient is not a valid Australian mobile number.',
            );

            $this->events->dispatch(new SmsFailed($message, $result));
            $this->writeLogChannel($result);

            return $result;
        }

        $existing = $this->logger->findIdempotent($message);
        if ($existing !== null) {
            return $existing;
        }

        $this->events->dispatch(new SmsSending($message, $message->connection));
        $log = $this->logger->record($message, SmsStatus::Pending);

        Context::add('sms_uuid', $message->uuid);
        Context::add('sms_connection', $message->connection);

        try {
            $result = $this->attempt($message);
            $this->logger->complete($log, $result);

            if ($result->successful) {
                $this->events->dispatch(new SmsSent($message, $result));
            } else {
                $this->events->dispatch(new SmsFailed($message, $result));
            }

            $this->writeLogChannel($result);

            return $result;
        } finally {
            Context::forget('sms_uuid');
            Context::forget('sms_connection');
        }
    }

    /**
     * @param  array<int, SmsMessage>  $messages
     */
    public function queueMessages(array $messages, DateTimeInterface|int|null $delay = null): PendingDispatch|Batch
    {
        foreach ($messages as $message) {
            $this->logger->record($message, SmsStatus::Queued);
            $this->events->dispatch(new SmsQueued($message, $message->connection));
        }

        $jobs = array_map(
            fn (SmsMessage $message): SendSms => new SendSms($message, $message->connection),
            $messages,
        );

        if (count($jobs) === 1) {
            $pending = new PendingDispatch($jobs[0]);

            if ($delay !== null) {
                $pending->delay($delay);
            }

            return $pending;
        }

        $batch = Bus::batch($jobs);

        if ($delay !== null) {
            foreach ($jobs as $job) {
                $job->delay($delay);
            }
        }

        $queueConnection = config('sms.queue.connection');
        if (is_string($queueConnection) && $queueConnection !== '') {
            $batch->onConnection($queueConnection);
        }

        $queue = config('sms.queue.queue');
        if (is_string($queue) && $queue !== '') {
            $batch->onQueue($queue);
        }

        return $batch->dispatch();
    }

    private function attempt(SmsMessage $message): SmsResult
    {
        if (config('sms.pretend')) {
            return SmsResult::accepted(
                message: $message,
                smsId: random_int(1, 999_999_999),
                parts: MessageEncoding::segments($message->body),
                price: 0.0,
            );
        }

        $config = $this->client->connectionConfig($message->connection);

        return $this->client->send($message, $config);
    }

    private function writeLogChannel(SmsResult $result): void
    {
        if (! config('sms.log_channel.enabled')) {
            return;
        }

        $channel = config('sms.log_channel.channel');
        $level = (string) config('sms.log_channel.level', 'info');
        $logger = is_string($channel) && $channel !== '' ? Log::channel($channel) : Log::getFacadeRoot();

        $logger->log($level, 'SMS {status}', [
            'uuid' => $result->uuid,
            'connection' => $result->connection,
            'recipient' => $result->recipient,
            'status' => $result->status->value,
            'error_type' => $result->errorType?->value,
        ]);
    }
}
