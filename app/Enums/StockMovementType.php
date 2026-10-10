<?php

namespace App\Enums;

enum StockMovementType: string
{
    case OrderDeduct = 'order_deduct';
    case OrderRestock = 'order_restock';
    case ManualAdjustment = 'manual_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::OrderDeduct => 'Order deduction',
            self::OrderRestock => 'Order restock',
            self::ManualAdjustment => 'Manual adjustment',
        };
    }
}
