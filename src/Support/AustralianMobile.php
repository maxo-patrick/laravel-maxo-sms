<?php

namespace MaxoTel\Sms\Support;

final class AustralianMobile
{
    public static function isValid(string $number): bool
    {
        return self::toE164($number) !== null;
    }

    public static function toE164(string $number): ?string
    {
        $digits = self::digits($number);

        if (preg_match('/^04\d{8}$/', $digits) === 1) {
            return '+61'.substr($digits, 1);
        }

        if (preg_match('/^614\d{8}$/', $digits) === 1) {
            return '+'.$digits;
        }

        return null;
    }

    public static function toLocal04(string $e164): string
    {
        $digits = self::digits($e164);

        if (preg_match('/^614\d{8}$/', $digits) === 1) {
            return '0'.substr($digits, 2);
        }

        if (preg_match('/^04\d{8}$/', $digits) === 1) {
            return $digits;
        }

        return $e164;
    }

    public static function digits(string $number): string
    {
        $stripped = preg_replace('/[\s\-\(\)\.]/', '', $number) ?? $number;
        $stripped = ltrim($stripped, '+');

        return $stripped;
    }
}
