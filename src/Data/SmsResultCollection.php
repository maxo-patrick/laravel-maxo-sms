<?php

namespace MaxoTel\Sms\Data;

use Illuminate\Support\Collection;

/**
 * @extends Collection<int, SmsResult>
 */
class SmsResultCollection extends Collection
{
    /**
     * @return self<int, SmsResult>
     */
    public function successful(): self
    {
        return $this->filter(fn (SmsResult $result): bool => $result->successful)->values();
    }

    /**
     * @return self<int, SmsResult>
     */
    public function failed(): self
    {
        return $this->filter(fn (SmsResult $result): bool => $result->failed())->values();
    }

    public function allSucceeded(): bool
    {
        return $this->isNotEmpty() && $this->failed()->isEmpty();
    }

    public function totalPrice(): float
    {
        return (float) $this->sum(fn (SmsResult $result): float => $result->price ?? 0.0);
    }
}
