<?php

namespace MaxoTel\Sms\Logging;

use MaxoTel\Sms\Data\SmsMessage;
use MaxoTel\Sms\Data\SmsResult;
use MaxoTel\Sms\Enums\SmsStatus;
use MaxoTel\Sms\Models\SmsLog;

/**
 * @internal
 */
final class SmsLogger
{
    public function enabled(): bool
    {
        return (bool) config('sms.logging.enabled');
    }

    public function findIdempotent(SmsMessage $message): ?SmsResult
    {
        if (! $this->enabled() || $message->idempotencyKey === null) {
            return null;
        }

        $existing = $this->model()->newQuery()
            ->where('connection', $message->connection)
            ->where('idempotency_key', $message->idempotencyKey)
            ->where('recipient', $message->recipient)
            ->first();

        return $existing?->toResult();
    }

    public function record(SmsMessage $message, SmsStatus $status): ?SmsLog
    {
        if (! $this->enabled()) {
            return null;
        }

        $existing = $this->model()->newQuery()->where('uuid', $message->uuid)->first();

        if ($existing) {
            $existing->status = $status;
            $existing->save();

            return $existing;
        }

        $log = $this->model()::fromMessage($message, $status);
        $log->save();

        return $log;
    }

    public function complete(?SmsLog $log, SmsResult $result): void
    {
        if (! $this->enabled()) {
            return;
        }

        $log ??= $this->model()->newQuery()->where('uuid', $result->uuid)->first();

        if ($log === null) {
            $message = new SmsMessage(
                uuid: $result->uuid,
                recipient: $result->recipient,
                body: '',
                sender: $result->sender,
                smsInbox: $result->smsInbox,
                connection: $result->connection,
            );
            $log = $this->model()::fromMessage($message, $result->status);
            $log->save();
        }

        $log->applyResult($result);
    }

    private function model(): SmsLog
    {
        /** @var class-string<SmsLog> $class */
        $class = config('sms.logging.model', SmsLog::class);

        return new $class;
    }
}
