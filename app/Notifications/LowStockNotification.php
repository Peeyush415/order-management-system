<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    public function __construct(private readonly Product $product) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'low_stock',
            'message' => "Low stock: {$this->product->name} has {$this->product->stock_quantity} left.",
            'product_id' => $this->product->id,
        ];
    }
}
