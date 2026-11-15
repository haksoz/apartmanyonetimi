<?php

namespace App\Support;

use InvalidArgumentException;

class PaymentAmount
{
    public static function minor(string $amount): int
    {
        if (! preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', $amount, $parts)) {
            throw new InvalidArgumentException('Geçersiz ödeme tutarı.');
        }

        return ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
    }
}
