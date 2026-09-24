<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function minor(mixed $value): int
    {
        $value = (string) $value;
        if (! preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $value)) {
            throw new InvalidArgumentException('Amount must be non-negative with at most two decimal places.');
        }

        return (int) bcmul($value, '100', 0);
    }

    public static function major(int $minor): float
    {
        return (float) bcdiv((string) $minor, '100', 2);
    }
}
