<?php

namespace MaxoTel\Sms\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use MaxoTel\Sms\Support\SerializesData;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class SmsInbox implements Arrayable, JsonSerializable
{
    use SerializesData;

    public function __construct(
        public int $id,
        public string $name,
        public string $senderId,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? $data['smsinbox'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            senderId: (string) ($data['senderId'] ?? $data['senderid'] ?? ''),
        );
    }
}
