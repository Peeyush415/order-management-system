<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Every stock change goes through here and writes one ledger row, so a product's
 * stock_quantity always equals the sum of its stock_movements.
 *
 * Deductions use one conditional UPDATE per product:
 *   UPDATE products SET stock_quantity = stock_quantity - :qty WHERE id = :id AND stock_quantity >= :qty
 * The check and the write are a single atomic statement, so two orders confirmed at the
 * same moment can never both take the last units (no read-then-write race, no overselling).
 * Callers run these methods inside their own transaction.
 */
class InventoryService
{
    /**
     * @throws InsufficientStockException (the caller's transaction then rolls back everything)
     */
    public function deductForOrder(Order $order, User $by): void
    {
        foreach ($this->quantitiesByProduct($order) as $productId => $quantity) {
            $this->take($productId, $quantity);
            $this->record($productId, $order->id, $by, StockMovementType::OrderDeduct, -$quantity);
        }
    }

    /**
     * Puts back what deductForOrder took. Only call it for orders whose stock was deducted.
     */
    public function restockForOrder(Order $order, User $by): void
    {
        foreach ($this->quantitiesByProduct($order) as $productId => $quantity) {
            Product::query()->whereKey($productId)->increment('stock_quantity', $quantity);
            $this->record($productId, $order->id, $by, StockMovementType::OrderRestock, $quantity);
        }
    }

    /**
     * Manual correction by an admin, e.g. goods received (+) or a stock count mismatch (−).
     *
     * @throws InsufficientStockException when a negative adjustment would go below zero
     */
    public function adjust(Product $product, int $delta, string $reason, User $by): Product
    {
        return DB::transaction(function () use ($product, $delta, $reason, $by) {
            if ($delta < 0) {
                $this->take($product->id, -$delta);
            } else {
                Product::query()->whereKey($product->id)->increment('stock_quantity', $delta);
            }

            $this->record($product->id, null, $by, StockMovementType::ManualAdjustment, $delta, $reason);

            return $product->fresh();
        });
    }

    /**
     * Atomic "subtract only if enough is left".
     *
     * @throws InsufficientStockException
     */
    private function take(int $productId, int $quantity): void
    {
        $updated = Product::query()
            ->whereKey($productId)
            ->where('stock_quantity', '>=', $quantity)
            ->decrement('stock_quantity', $quantity);

        if ($updated === 0) {
            $product = Product::withTrashed()->find($productId, ['id', 'name', 'stock_quantity']);

            throw new InsufficientStockException($product?->name ?? "#{$productId}", $product?->stock_quantity ?? 0, $quantity);
        }
    }

    /**
     * Quantities per product, in product id order: every transaction locks product rows
     * in the same order, which prevents deadlocks between concurrent confirmations.
     *
     * @return array<int, int>
     */
    private function quantitiesByProduct(Order $order): array
    {
        return $order->items()
            ->selectRaw('product_id, SUM(quantity) as quantity')
            ->groupBy('product_id')
            ->orderBy('product_id')
            ->pluck('quantity', 'product_id')
            ->map(fn ($quantity) => (int) $quantity)
            ->all();
    }

    private function record(int $productId, ?int $orderId, User $by, StockMovementType $type, int $quantity, ?string $reason = null): void
    {
        // Read after our own UPDATE in the same transaction: the row is still locked by us.
        $balance = (int) Product::query()->whereKey($productId)->value('stock_quantity');

        StockMovement::create([
            'product_id' => $productId,
            'order_id' => $orderId,
            'user_id' => $by->id,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $balance,
            'reason' => $reason,
        ]);
    }
}
