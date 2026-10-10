<?php

namespace App\Support;

/**
 * Money is stored as integer cents. Formatting uses integer maths only, never floats.
 */
class Money
{
    /** 12550 → "125.50", 123456789 → "1,234,567.89", -5 → "-0.05" */
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.number_format(intdiv($cents, 100)).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
