<?php

namespace MaxoTel\Sms\Data;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use MaxoTel\Sms\Enums\SmsErrorType;
use MaxoTel\Sms\Enums\SmsStatus;
use MaxoTel\Sms\Exceptions\SmsSendException;
use MaxoTel\Sms\Support\SerializesData;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class SmsResult implements Arrayable, JsonSerializable
{
    use SerializesData;

    /**
     * @param  array<string, mixed>|null  $providerResponse
     */
    public function __construct(
        public bool $successful,
        public string $uuid,
        public string $connection,
        public SmsStatus $status,
        public string $recipient,
        public ?string $sender = null,
        public ?int $smsId = null,
        public ?int $smsInbox = null,
        public ?int $parts = null,
        public ?float $price = null,
        public ?SmsErrorType $errorType = null,
        public ?string $errorMessage = null,
        public ?string $providerError = null,
        public ?string $providerReason = null,
        public ?array $providerResponse = null,
        public ?CarbonImmutable $sentAt = null,
    ) {}

    public function failed(): bool
    {
        return ! $this->successful;
    }

    public function throw(): static
    {
        if ($this->failed()) {
            throw new SmsSendException($this);
        }

        return $this;
    }

    public function throwIf(bool $condition): static
    {
        if ($condition) {
            return $this->throw();
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>|null  $providerResponse
     */
    public static function accepted(
        SmsMessage $message,
        int $smsId,
        ?int $parts,
        ?float $price,
        ?array $providerResponse = null,
    ): self {
        return new self(
            successful: true,
            uuid: $message->uuid,
            connection: $message->connection,
            status: SmsStatus::Sent,
            recipient: $message->recipient,
            sender: $message->sender,
            smsId: $smsId,
            smsInbox: $message->smsInbox,
            parts: $parts,
            price: $price,
            providerResponse: $providerResponse,
            sentAt: CarbonImmutable::now(),
        );
    }

    /**
     * @param  array<string, mixed>|null  $providerResponse
     */
    public static function failure(
        SmsMessage $message,
        SmsErrorType $errorType,
        SmsStatus $status = SmsStatus::Failed,
        ?string $errorMessage = null,
        ?string $providerError = null,
        ?string $providerReason = null,
        ?array $providerResponse = null,
    ): self {
        return new self(
            successful: false,
            uuid: $message->uuid,
            connection: $message->connection,
            status: $status,
            recipient: $message->recipient,
            sender: $message->sender,
            smsInbox: $message->smsInbox,
            errorType: $errorType,
            errorMessage: $errorMessage ?? $providerReason ?? $errorType->value,
            providerError: $providerError,
            providerReason: $providerReason,
            providerResponse: $providerResponse,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $status = self::enumFrom($data, 'status', SmsStatus::class);
        $errorType = self::enumFrom($data, 'errorType', SmsErrorType::class);

        return new self(
            successful: (bool) $data['successful'],
            uuid: (string) $data['uuid'],
            connection: (string) $data['connection'],
            status: $status instanceof SmsStatus ? $status : SmsStatus::Failed,
            recipient: (string) $data['recipient'],
            sender: isset($data['sender']) ? (string) $data['sender'] : null,
            smsId: isset($data['smsId']) ? (int) $data['smsId'] : null,
            smsInbox: isset($data['smsInbox']) ? (int) $data['smsInbox'] : null,
            parts: isset($data['parts']) ? (int) $data['parts'] : null,
            price: isset($data['price']) ? (float) $data['price'] : null,
            errorType: $errorType instanceof SmsErrorType ? $errorType : null,
            errorMessage: isset($data['errorMessage']) ? (string) $data['errorMessage'] : null,
            providerError: isset($data['providerError']) ? (string) $data['providerError'] : null,
            providerReason: isset($data['providerReason']) ? (string) $data['providerReason'] : null,
            providerResponse: is_array($data['providerResponse'] ?? null) ? $data['providerResponse'] : null,
            sentAt: self::dateFrom($data, 'sentAt'),
        );
    }
}
