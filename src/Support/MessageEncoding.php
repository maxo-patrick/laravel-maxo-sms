<?php

namespace MaxoTel\Sms\Support;

final class MessageEncoding
{
    public static function isGsm7(string $body): bool
    {
        $length = mb_strlen($body, 'UTF-8');

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($body, $i, 1, 'UTF-8');

            if (! str_contains(self::gsmBasic(), $char) && ! str_contains(self::gsmExtended(), $char)) {
                return false;
            }
        }

        return true;
    }

    public static function segments(string $body): int
    {
        $units = self::isGsm7($body) ? self::gsm7Units($body) : mb_strlen($body, 'UTF-8');
        $singleLimit = self::isGsm7($body) ? 160 : 70;
        $concatLimit = self::isGsm7($body) ? 153 : 67;

        if ($units === 0) {
            return 1;
        }

        if ($units <= $singleLimit) {
            return 1;
        }

        return (int) ceil($units / $concatLimit);
    }

    private static function gsm7Units(string $body): int
    {
        $units = 0;
        $length = mb_strlen($body, 'UTF-8');

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($body, $i, 1, 'UTF-8');
            $units += str_contains(self::gsmExtended(), $char) ? 2 : 1;
        }

        return $units;
    }

    private static function gsmBasic(): string
    {
        return '@£$¥èéùìòÇ'."\n".'Øø'."\r".'ÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
    }

    private static function gsmExtended(): string
    {
        return '^{}\\[~]|€';
    }
}
