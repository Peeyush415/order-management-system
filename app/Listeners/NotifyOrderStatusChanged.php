<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Models\User;
use App\Notifications\OrderStatusChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the assignee and the creator when an order is confirmed, cancelled or completed.
 * Queued after commit; the person who made the change is not notified of their own action.
 */
class NotifyOrderStatusChanged implements ShouldQueueAfterCommit
{
    private const NOTIFY_ON = [OrderStatus::Confirmed, OrderStatus::Cancelled, OrderStatus::Completed];

    public function handle(OrderStatusChanged $event): void
    {
        if (! in_array($event->to, self::NOTIFY_ON, true)) {
            return;
        }

        $recipientIds = collect([$event->order->assigned_to, $event->order->created_by])
            ->filter()
            ->unique()
            ->reject(fn (int $id) => $id === $event->by->id);

        if ($recipientIds->isEmpty()) {
            return;
        }

        $recipients = User::query()->whereKey($recipientIds->all())->where('is_active', true)->get();
        Notification::send($recipients, new OrderStatusChangedNotification($event->order, $event->to));
    }
}
