<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the order list: only what the table shows, no items.
 *
 * @mixin Order
 */
class OrderListResource extends JsonResource
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
            ]),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? new UserSummaryResource($this->assignee) : null),
            'grand_total' => $this->grand_total,
            'grand_total_formatted' => Money::format($this->grand_total),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
