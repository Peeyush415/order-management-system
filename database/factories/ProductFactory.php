<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->bothify('SKU-??##??'),
            'name' => ucwords(fake()->words(fake()->numberBetween(2, 3), true)),
            'description' => fake()->optional(0.7)->sentence(12),
            'price' => fake()->numberBetween(5, 500) * 100, // cents: 5.00 to 500.00
            'tax_rate_bps' => fake()->randomElement([0, 500, 1000, 1800]),
            'stock_quantity' => fake()->numberBetween(20, 300),
            'low_stock_threshold' => 10,
            'status' => ProductStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProductStatus::Inactive]);
    }

    public function discontinued(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProductStatus::Discontinued]);
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => ['stock_quantity' => fake()->numberBetween(0, 9)]);
    }
}
