<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductService
{
    /**
     * Creates the product and, if it starts with stock, the matching opening ledger entry,
     * so stock_quantity always equals the sum of the product's stock movements.
     *
     * @param  array<string, mixed>  $data  validated input without initial_stock
     */
    public function create(array $data, int $initialStock, User $by): Product
    {
        return DB::transaction(function () use ($data, $initialStock, $by) {
            $product = new Product($data);
            $product->stock_quantity = $initialStock;
            $product->save();

            if ($initialStock > 0) {
                $product->stockMovements()->create([
                    'user_id' => $by->id,
                    'type' => StockMovementType::ManualAdjustment,
                    'quantity' => $initialStock,
                    'balance_after' => $initialStock,
                    'reason' => 'Opening stock',
                ]);
            }

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated input (never stock)
     */
    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product;
    }

    public function changeStatus(Product $product, ProductStatus $status): Product
    {
        $product->update(['status' => $status]);

        return $product;
    }
}
