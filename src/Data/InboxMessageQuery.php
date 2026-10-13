<?php

namespace MaxoTel\Sms\Data;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final readonly class InboxMessageQuery
{
    public function __construct(
        public DateTimeInterface|int|string $startTime,
        public DateTimeInterface|int|string $endTime,
        public ?string $contact = null,
    ) {}

    public function startTimestamp(): int
    {
        return $this->toTimestamp($this->startTime);
    }

    public function endTimestamp(): int
    {
        return $this->toTimestamp($this->endTime);
    }

    private function toTimestamp(DateTimeInterface|int|string $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return CarbonImmutable::parse($value)->getTimestamp();
    }
}
