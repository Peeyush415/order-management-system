<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Snapshots the product and computes simple line amounts (no order discount).
 *
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 5),
            'product_name' => fn (array $attributes) => Product::find($attributes['product_id'])->name,
            'sku' => fn (array $attributes) => Product::find($attributes['product_id'])->sku,
            'unit_price' => fn (array $attributes) => Product::find($attributes['product_id'])->price,
            'tax_rate_bps' => fn (array $attributes) => Product::find($attributes['product_id'])->tax_rate_bps,
            'line_subtotal' => fn (array $attributes) => $attributes['quantity'] * $attributes['unit_price'],
            'discount_amount' => 0,
            // Round half up in integer cents.
            'tax_amount' => fn (array $attributes) => intdiv($attributes['line_subtotal'] * $attributes['tax_rate_bps'] + 5000, 10000),
            'line_total' => fn (array $attributes) => $attributes['line_subtotal'] + $attributes['tax_amount'],
        ];
    }
}
