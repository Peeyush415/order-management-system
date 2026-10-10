<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// stock_quantity is not fillable: only the inventory service changes stock.
#[Fillable([
    'sku', 'name', 'description',
    'price', 'tax_rate_bps', 'low_stock_threshold',
    'status',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'tax_rate_bps' => 'integer',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'status' => ProductStatus::class,
        ];
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->low_stock_threshold;
    }
}
