<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once, when a product's stock drops to or below its low-stock threshold.
 */
class ProductStockLow
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Product $product) {}
}
