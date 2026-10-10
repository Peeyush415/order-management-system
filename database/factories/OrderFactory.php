<?php

namespace Database\Factories;

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Totals stay at zero here; real amounts come from the pricing calculator
 * (order service) and the demo seeder.
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'assigned_to' => null,
            'created_by' => User::factory(),
            'status' => OrderStatus::Pending,
            'priority' => OrderPriority::Normal,
            'order_date' => fake()->dateTimeBetween('-6 months')->format('Y-m-d'),
            'discount_type' => null,
            'discount_value' => 0,
        ];
    }

    public function configure(): static
    {
        // Same rule as the order service: the number is derived from the id after insert.
        return $this->afterCreating(function (Order $order) {
            $order->forceFill(['order_number' => Order::numberFor($order->id)])->save();
        });
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => OrderStatus::Draft]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => OrderStatus::Pending]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => ['assigned_to' => $user->id]);
    }
}
