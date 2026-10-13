<?php

namespace MaxoTel\Sms\Data;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use MaxoTel\Sms\Enums\MessageDirection;
use MaxoTel\Sms\Support\SerializesData;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class InboxMessage implements Arrayable, JsonSerializable
{
    use SerializesData;

    public function __construct(
        public int $smsId,
        public int $smsInbox,
        public CarbonImmutable $sentAt,
        public string $contact,
        public MessageDirection $direction,
        public string $origin,
        public string $destination,
        public string $status,
        public bool $read,
        public float $cost,
        public string $body,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $direction = self::enumFrom($data, 'direction', MessageDirection::class);

        return new self(
            smsId: (int) $data['smsId'],
            smsInbox: (int) $data['smsInbox'],
            sentAt: self::dateFrom($data, 'sentAt') ?? CarbonImmutable::now(),
            contact: (string) $data['contact'],
            direction: $direction instanceof MessageDirection ? $direction : MessageDirection::Send,
            origin: (string) $data['origin'],
            destination: (string) $data['destination'],
            status: (string) $data['status'],
            read: (bool) $data['read'],
            cost: (float) $data['cost'],
            body: (string) $data['body'],
        );
    }
}
