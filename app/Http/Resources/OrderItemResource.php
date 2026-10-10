<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Item as it was at order time (snapshot), amounts in cents plus display strings.
 *
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'tax_rate_bps' => $this->tax_rate_bps,
            'line_subtotal' => $this->line_subtotal,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'line_total' => $this->line_total,
            'formatted' => [
                'unit_price' => Money::format($this->unit_price),
                'discount_amount' => Money::format($this->discount_amount),
                'tax_amount' => Money::format($this->tax_amount),
                'line_total' => Money::format($this->line_total),
            ],
        ];
    }
}
