<?php

namespace App\Http\Resources;

use App\Models\Customer;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /** Order summary, included on the detail endpoint only. */
    private ?array $orderStats = null;

    public function withStats(array $stats): static
    {
        $this->orderStats = $stats;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'orders_count' => $this->whenCounted('orders'),
            'stats' => $this->when($this->orderStats !== null, fn () => [
                ...$this->orderStats,
                'total_order_value_formatted' => Money::format($this->orderStats['total_order_value']),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
