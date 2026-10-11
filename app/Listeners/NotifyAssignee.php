<?php

namespace App\Listeners;

use App\Events\OrderAssigned;
use App\Events\OrderCreated;
use App\Models\User;
use App\Notifications\OrderAssignedNotification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * "New order … assigned to you." Queued, and only after the transaction commits:
 * an order that rolled back never notifies anyone, and the request isn't slowed down.
 */
class NotifyAssignee implements ShouldQueueAfterCommit
{
    public function handle(OrderCreated|OrderAssigned $event): void
    {
        $assigneeId = $event->order->assigned_to;

        // Nobody to tell, or the user assigned the order to themselves.
        if (! $assigneeId || $assigneeId === $event->by->id) {
            return;
        }

        User::query()->whereKey($assigneeId)->where('is_active', true)->first()
            ?->notify(new OrderAssignedNotification($event->order));
    }
}
