<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Notifications\Notification;

class OrderStatusChangedNotification extends Notification
{
    public function __construct(
        private readonly Order $order,
        private readonly OrderStatus $status,
    ) {}

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
        $number = $this->order->order_number;

        return [
            'kind' => 'order_status_changed',
            'message' => match ($this->status) {
                OrderStatus::Confirmed => "Order {$number} has been confirmed.",
                OrderStatus::Cancelled => "Order {$number} was cancelled.",
                OrderStatus::Completed => "Order {$number} has been completed.",
                default => "Order {$number} is now {$this->status->label()}.",
            },
            'order_id' => $this->order->id,
            'order_number' => $number,
            'status' => $this->status->value,
        ];
    }
}
