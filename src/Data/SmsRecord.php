<?php

namespace MaxoTel\Sms\Data;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use MaxoTel\Sms\Enums\SmsErrorType;
use MaxoTel\Sms\Enums\SmsStatus;
use MaxoTel\Sms\Support\SerializesData;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class SmsRecord implements Arrayable, JsonSerializable
{
    use SerializesData;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $uuid,
        public string $connection,
        public string $recipient,
        public SmsStatus $status,
        public ?string $sender,
        public ?string $body,
        public ?int $smsId,
        public ?int $smsInbox,
        public ?string $reference,
        public ?int $parts,
        public ?float $price,
        public array $metadata,
        public ?SmsErrorType $errorType,
        public ?string $errorMessage,
        public ?string $providerError,
        public ?string $providerReason,
        public ?CarbonImmutable $queuedAt,
        public ?CarbonImmutable $sentAt,
        public ?CarbonImmutable $failedAt,
        public CarbonImmutable $createdAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $status = self::enumFrom($data, 'status', SmsStatus::class);
        $errorType = self::enumFrom($data, 'errorType', SmsErrorType::class);

        return new self(
            uuid: (string) $data['uuid'],
            connection: (string) $data['connection'],
            recipient: (string) $data['recipient'],
            status: $status instanceof SmsStatus ? $status : SmsStatus::Failed,
            sender: isset($data['sender']) ? (string) $data['sender'] : null,
            body: isset($data['body']) ? (string) $data['body'] : null,
            smsId: isset($data['smsId']) ? (int) $data['smsId'] : null,
            smsInbox: isset($data['smsInbox']) ? (int) $data['smsInbox'] : null,
            reference: isset($data['reference']) ? (string) $data['reference'] : null,
            parts: isset($data['parts']) ? (int) $data['parts'] : null,
            price: isset($data['price']) ? (float) $data['price'] : null,
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            errorType: $errorType instanceof SmsErrorType ? $errorType : null,
            errorMessage: isset($data['errorMessage']) ? (string) $data['errorMessage'] : null,
            providerError: isset($data['providerError']) ? (string) $data['providerError'] : null,
            providerReason: isset($data['providerReason']) ? (string) $data['providerReason'] : null,
            queuedAt: self::dateFrom($data, 'queuedAt'),
            sentAt: self::dateFrom($data, 'sentAt'),
            failedAt: self::dateFrom($data, 'failedAt'),
            createdAt: self::dateFrom($data, 'createdAt') ?? CarbonImmutable::now(),
        );
    }
}
