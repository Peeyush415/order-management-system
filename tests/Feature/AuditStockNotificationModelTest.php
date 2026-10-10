<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\TestCase;

class AuditStockNotificationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_points_to_its_record_and_keeps_json_diffs(): void
    {
        $order = Order::factory()->create();

        $log = AuditLog::create([
            'user_id' => $order->created_by,
            'event' => 'updated',
            'auditable_type' => $order->getMorphClass(),
            'auditable_id' => $order->id,
            'old_values' => ['priority' => 2],
            'new_values' => ['priority' => 4],
            'ip_address' => '127.0.0.1',
        ])->fresh();

        $this->assertTrue($log->auditable->is($order));
        $this->assertSame(['priority' => 4], $log->new_values);
        $this->assertArrayNotHasKey('updated_at', $log->getAttributes());
    }

    public function test_stock_ledger_stores_signed_quantities(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 50]);
        $order = Order::factory()->create();

        $product->stockMovements()->create([
            'order_id' => $order->id,
            'type' => StockMovementType::OrderDeduct,
            'quantity' => -5,
            'balance_after' => 45,
        ]);
        $product->stockMovements()->create([
            'order_id' => $order->id,
            'type' => StockMovementType::OrderRestock,
            'quantity' => 5,
            'balance_after' => 50,
        ]);

        $movements = $product->stockMovements()->get();

        $this->assertSame([5, -5], $movements->pluck('quantity')->all());
        $this->assertSame(StockMovementType::OrderRestock, $movements->first()->type);
        $this->assertSame(0, $movements->sum('quantity'));
    }

    public function test_user_receives_database_notifications_with_unread_count(): void
    {
        $user = User::factory()->create();

        $user->notify(new class extends Notification
        {
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toArray(object $notifiable): array
            {
                return ['message' => 'Order ORD-10001 assigned to you'];
            }
        });

        $this->assertSame(1, $user->unreadNotifications()->count());
        $this->assertSame('Order ORD-10001 assigned to you', $user->notifications->first()->data['message']);

        $user->unreadNotifications->markAsRead();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }
}
