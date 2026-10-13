<?php

namespace MaxoTel\Sms\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use MaxoTel\Sms\Support\SerializesData;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class SmsMessage implements Arrayable, JsonSerializable
{
    use SerializesData;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $uuid,
        public string $recipient,
        public string $body,
        public ?string $sender = null,
        public ?int $smsInbox = null,
        public ?string $reference = null,
        public array $metadata = [],
        public ?string $idempotencyKey = null,
        public string $connection = 'maxo',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: (string) $data['uuid'],
            recipient: (string) $data['recipient'],
            body: (string) ($data['body'] ?? ''),
            sender: isset($data['sender']) ? (string) $data['sender'] : null,
            smsInbox: isset($data['smsInbox']) ? (int) $data['smsInbox'] : null,
            reference: isset($data['reference']) ? (string) $data['reference'] : null,
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            idempotencyKey: isset($data['idempotencyKey']) ? (string) $data['idempotencyKey'] : null,
            connection: (string) ($data['connection'] ?? 'maxo'),
        );
    }
}
