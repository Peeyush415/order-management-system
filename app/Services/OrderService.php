<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\OrderActivityType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Exceptions\ProductUnavailableException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(private readonly OrderPricingCalculator $calculator) {}

    /**
     * Creates the order, its item snapshots, the first status history row and the activity
     * entry in one transaction: either all of it exists, or none of it.
     * Stock is not touched here; it is deducted when the order is confirmed.
     *
     * @param  array<string, mixed>  $data  validated input
     *
     * @throws ProductUnavailableException
     */
    public function create(array $data, User $by): Order
    {
        return DB::transaction(function () use ($data, $by) {
            $discountType = isset($data['discount_type']) ? DiscountType::from($data['discount_type']) : null;
            $pricing = $this->calculator->calculate(
                $this->snapshotLines($data['items']),
                $discountType,
                $discountType ? (int) $data['discount_value'] : 0,
            );
            $status = OrderStatus::from($data['status'] ?? OrderStatus::Pending->value);

            $order = new Order([
                'customer_id' => $data['customer_id'],
                'assigned_to' => $data['assigned_to'] ?? null,
                'priority' => OrderPriority::from((int) ($data['priority'] ?? OrderPriority::Normal->value)),
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'discount_type' => $discountType,
                'discount_value' => $discountType ? (int) $data['discount_value'] : 0,
            ]);
            // Set by the server only, never from input.
            $order->forceFill([
                'status' => $status,
                'created_by' => $by->id,
                'subtotal' => $pricing['subtotal'],
                'discount_total' => $pricing['discount_total'],
                'tax_total' => $pricing['tax_total'],
                'grand_total' => $pricing['grand_total'],
                'version' => 1,
            ])->save();

            // Derived from the id inside the transaction, so two requests can never share a number.
            $order->forceFill(['order_number' => Order::numberFor($order->id)])->save();

            $now = now();
            OrderItem::insert(array_map(
                fn (array $line) => $line + ['order_id' => $order->id, 'created_at' => $now, 'updated_at' => $now],
                array_values($pricing['lines']),
            ));

            $order->statusHistories()->create([
                'from_status' => null,
                'to_status' => $status,
                'changed_by' => $by->id,
            ]);
            $order->activities()->create([
                'user_id' => $by->id,
                'type' => OrderActivityType::Created,
                'description' => 'Order created as '.$status->label(),
                'properties' => ['status' => $status->value, 'grand_total' => $pricing['grand_total']],
            ]);

            if (! empty($data['note'])) {
                $order->notes()->create(['user_id' => $by->id, 'body' => $data['note']]);
                $order->activities()->create([
                    'user_id' => $by->id,
                    'type' => OrderActivityType::NoteAdded,
                    'description' => 'Note added',
                ]);
            }

            return $order;
        });
    }

    /**
     * Loads all products in one query and copies what the order must remember
     * (name, SKU, price, tax rate), so later product edits never change this order.
     *
     * @param  list<array{product_id: int, quantity: int}>  $items
     * @return list<array<string, mixed>>
     *
     * @throws ProductUnavailableException
     */
    private function snapshotLines(array $items): array
    {
        $products = Product::query()
            ->whereIn('id', array_column($items, 'product_id'))
            ->get(['id', 'name', 'sku', 'price', 'tax_rate_bps', 'status'])
            ->keyBy('id');

        return array_map(function (array $item) use ($products) {
            $product = $products->get($item['product_id']);

            // Validation checked this, but the product may have changed since.
            if (! $product || $product->status !== ProductStatus::Active) {
                throw new ProductUnavailableException((int) $item['product_id']);
            }

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'unit_price' => $product->price,
                'tax_rate_bps' => $product->tax_rate_bps,
                'quantity' => (int) $item['quantity'],
            ];
        }, array_values($items));
    }
}
