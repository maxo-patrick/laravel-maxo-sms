<?php

namespace MaxoTel\Sms\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use MaxoTel\Sms\Data\SmsMessage;
use MaxoTel\Sms\Data\SmsRecord;
use MaxoTel\Sms\Data\SmsResult;
use MaxoTel\Sms\Enums\SmsErrorType;
use MaxoTel\Sms\Enums\SmsStatus;

/**
 * @internal
 *
 * @property string $uuid
 * @property string $connection
 * @property string $recipient
 * @property string|null $sender
 * @property string|null $body
 * @property SmsStatus $status
 * @property int|null $sms_id
 * @property int|null $sms_inbox
 * @property string|null $reference
 * @property string|null $idempotency_key
 * @property int|null $parts
 * @property string|float|null $price
 * @property array<string, mixed>|null $metadata
 * @property SmsErrorType|null $error_type
 * @property string|null $error_message
 * @property string|null $provider_error
 * @property string|null $provider_reason
 * @property array<string, mixed>|null $provider_response
 * @property CarbonInterface|null $queued_at
 * @property CarbonInterface|null $sent_at
 * @property CarbonInterface|null $failed_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class SmsLog extends Model
{
    use MassPrunable;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SmsStatus::class,
            'error_type' => SmsErrorType::class,
            'metadata' => 'array',
            'provider_response' => 'array',
            'sms_id' => 'integer',
            'sms_inbox' => 'integer',
            'parts' => 'integer',
            'price' => 'decimal:4',
            'queued_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    public function getTable(): string
    {
        return (string) config('sms.logging.table', 'sms_logs');
    }

    public function getConnectionName(): ?string
    {
        $connection = config('sms.logging.connection');

        return is_string($connection) && $connection !== '' ? $connection : parent::getConnectionName();
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = config('sms.logging.retain_for_days');

        if ($days === null) {
            return static::query()->whereRaw('0 = 1');
        }

        return static::query()->where('created_at', '<=', now()->subDays((int) $days));
    }

    public static function fromMessage(SmsMessage $message, SmsStatus $status): static
    {
        /** @var class-string<static> $class */
        $class = static::class;
        $model = new $class;
        $model->fill(self::messageAttributes($message, $status));

        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    public static function messageAttributes(SmsMessage $message, SmsStatus $status): array
    {
        return [
            'uuid' => $message->uuid,
            'connection' => $message->connection,
            'recipient' => $message->recipient,
            'sender' => $message->sender,
            'body' => config('sms.logging.store_body') ? $message->body : null,
            'status' => $status,
            'sms_inbox' => $message->smsInbox,
            'reference' => $message->reference,
            'idempotency_key' => $message->idempotencyKey,
            'metadata' => $message->metadata,
            'queued_at' => $status === SmsStatus::Queued ? CarbonImmutable::now() : null,
        ];
    }

    public function applyResult(SmsResult $result): void
    {
        $this->status = $result->status;
        $this->sms_id = $result->smsId;
        $this->parts = $result->parts;
        $this->price = $result->price;
        $this->error_type = $result->errorType;
        $this->error_message = $result->errorMessage;
        $this->provider_error = $result->providerError;
        $this->provider_reason = $result->providerReason;
        $this->provider_response = config('sms.logging.store_provider_response') ? $result->providerResponse : null;
        $this->sent_at = $result->sentAt;
        $this->failed_at = $result->failed() ? CarbonImmutable::now() : null;
        $this->save();
    }

    public function toRecord(): SmsRecord
    {
        return new SmsRecord(
            uuid: $this->uuid,
            connection: $this->connection,
            recipient: $this->recipient,
            status: $this->status,
            sender: $this->sender,
            body: $this->body,
            smsId: $this->sms_id,
            smsInbox: $this->sms_inbox,
            reference: $this->reference,
            parts: $this->parts,
            price: $this->price !== null ? (float) $this->price : null,
            metadata: $this->metadata ?? [],
            errorType: $this->error_type,
            errorMessage: $this->error_message,
            providerError: $this->provider_error,
            providerReason: $this->provider_reason,
            queuedAt: self::immutable($this->queued_at),
            sentAt: self::immutable($this->sent_at),
            failedAt: self::immutable($this->failed_at),
            createdAt: self::immutable($this->created_at) ?? CarbonImmutable::now(),
        );
    }

    public function toResult(): SmsResult
    {
        return new SmsResult(
            successful: $this->status === SmsStatus::Sent,
            uuid: $this->uuid,
            connection: $this->connection,
            status: $this->status,
            recipient: $this->recipient,
            sender: $this->sender,
            smsId: $this->sms_id,
            smsInbox: $this->sms_inbox,
            parts: $this->parts,
            price: $this->price !== null ? (float) $this->price : null,
            errorType: $this->error_type,
            errorMessage: $this->error_message,
            providerError: $this->provider_error,
            providerReason: $this->provider_reason,
            providerResponse: $this->provider_response,
            sentAt: self::immutable($this->sent_at),
        );
    }

    private static function immutable(?\DateTimeInterface $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::instance($value);
    }
}
