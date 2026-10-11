<?php

namespace App\Listeners;

use App\Events\ProductStockLow;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Notification;

/**
 * Alerts the people who can restock (products.manage) when a product runs low.
 */
class NotifyLowStock implements ShouldQueueAfterCommit
{
    public function handle(ProductStockLow $event): void
    {
        $managers = User::permission('products.manage')->where('is_active', true)->get();

        Notification::send($managers, new LowStockNotification($event->product));
    }
}
