<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full order for the detail page. Relations appear only when the caller eager-loaded them.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'priority' => ['value' => $this->priority->value, 'label' => $this->priority->label()],
            'order_date' => $this->order_date->toDateString(),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
                'company' => $this->customer->company,
                'address' => [
                    'line1' => $this->customer->address_line1,
                    'line2' => $this->customer->address_line2,
                    'city' => $this->customer->city,
                    'state' => $this->customer->state,
                    'postal_code' => $this->customer->postal_code,
                    'country' => $this->customer->country,
                ],
            ]),
            // Unassigned orders return null, not an empty object.
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? new UserSummaryResource($this->assignee) : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? new UserSummaryResource($this->creator) : null),
            'discount_type' => $this->discount_type?->value,
            'discount_value' => $this->discount_value,
            // Cents for calculations; formatted strings for display.
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'grand_total' => $this->grand_total,
            'formatted' => [
                'subtotal' => Money::format($this->subtotal),
                'discount_total' => Money::format($this->discount_total),
                'tax_total' => Money::format($this->tax_total),
                'grand_total' => Money::format($this->grand_total),
            ],
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            // Sent back on update for optimistic locking.
            'version' => $this->version,
            'stock_deducted_at' => $this->stock_deducted_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
