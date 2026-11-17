<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    private const int SCALE = 9;

    public static function fromDecimal(string $dollars): int
    {
        if (! is_numeric($dollars)) {
            throw new InvalidArgumentException("Not a decimal amount: {$dollars}");
        }

        return (int) bcmul($dollars, bcpow('10', (string) self::SCALE), 0);
    }

    public static function toDecimal(int $nanodollars): string
    {
        return bcdiv((string) $nanodollars, bcpow('10', (string) self::SCALE), self::SCALE);
    }
}
