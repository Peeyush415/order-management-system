<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

class OrderAssignedNotification extends Notification
{
    public function __construct(private readonly Order $order) {}

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
            'kind' => 'order_assigned',
            'message' => "New order {$this->order->order_number} assigned to you.",
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
        ];
    }
}
