<?php

namespace MaxoTel\Sms\Support;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;

trait SerializesData
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [];

        foreach (get_object_vars($this) as $key => $value) {
            $payload[$key] = $this->normalizeValue($value);
        }

        return $payload;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  class-string  $enumClass
     */
    protected static function enumFrom(array $data, string $key, string $enumClass): mixed
    {
        $value = $data[$key] ?? null;

        if ($value instanceof $enumClass) {
            return $value;
        }

        if (is_string($value) || is_int($value)) {
            return $enumClass::from($value);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function dateFrom(array $data, string $key): ?CarbonImmutable
    {
        $value = $data[$key] ?? null;

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_int($value) || (is_string($value) && is_numeric($value))) {
            return CarbonImmutable::createFromTimestamp((int) $value);
        }

        if (is_string($value) && $value !== '') {
            return CarbonImmutable::parse($value);
        }

        return null;
    }

    protected function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->toIso8601String();
        }

        return $value;
    }
}
