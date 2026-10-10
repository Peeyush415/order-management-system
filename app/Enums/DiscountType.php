<?php

namespace App\Enums;

/**
 * Fixed: discount_value is in cents. Percent: discount_value is in basis points (1000 = 10%).
 */
enum DiscountType: string
{
    case Fixed = 'fixed';
    case Percent = 'percent';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed amount',
            self::Percent => 'Percentage',
        };
    }
}
