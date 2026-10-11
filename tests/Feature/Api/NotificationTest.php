<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Events\OrderAssigned;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Events\ProductStockLow;
use App\Listeners\NotifyAssignee;
use App\Listeners\NotifyLowStock;
use App\Listeners\NotifyOrderStatusChanged;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Notifications\OrderAssignedNotification;
use App\Notifications\OrderStatusChangedNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create()->assignRole('admin');
        $this->manager = User::factory()->create()->assignRole('manager');
        $this->staff = User::factory()->create()->assignRole('staff');
    }

    private function product(int $stock, int $threshold = 10): Product
    {
        $product = Product::factory()->create(['stock_quantity' => $stock, 'low_stock_threshold' => $threshold, 'name' => 'Wireless Keyboard']);
        StockMovement::create(['product_id' => $product->id, 'type' => StockMovementType::ManualAdjustment, 'quantity' => $stock, 'balance_after' => $stock]);

        return $product;
    }

    private function pendingOrder(Product $product, int $quantity, ?User $assignee = null): Order
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending, 'assigned_to' => $assignee?->id, 'created_by' => $this->manager->id]);
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => $quantity]);

        return $order->fresh();
    }

    public function test_listeners_are_queued_after_commit(): void
    {
        foreach ([NotifyAssignee::class, NotifyOrderStatusChanged::class, NotifyLowStock::class] as $listener) {
            $this->assertInstanceOf(ShouldQueueAfterCommit::class, app($listener));
        }

        Event::fake();
        Event::assertListening(OrderCreated::class, NotifyAssignee::class);
        Event::assertListening(OrderAssigned::class, NotifyAssignee::class);
        Event::assertListening(OrderStatusChanged::class, NotifyOrderStatusChanged::class);
        Event::assertListening(ProductStockLow::class, NotifyLowStock::class);
    }

    public function test_assigning_an_order_notifies_the_new_assignee_only(): void
    {
        Notification::fake();
        $order = Order::factory()->create();

        $this->actingAs($this->manager)->postJson("/api/v1/orders/{$order->id}/assign", ['assigned_to' => $this->staff->id])->assertOk();

        Notification::assertSentTo($this->staff, OrderAssignedNotification::class, function ($notification) use ($order) {
            return $notification->toArray($this->staff)['message'] === "New order {$order->order_number} assigned to you.";
        });
        Notification::assertNotSentTo($this->manager, OrderAssignedNotification::class);
    }

    public function test_assigning_yourself_sends_nothing(): void
    {
        Notification::fake();
        $order = Order::factory()->create();

        $this->actingAs($this->manager)->postJson("/api/v1/orders/{$order->id}/assign", ['assigned_to' => $this->manager->id])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_creating_an_order_with_an_assignee_notifies_them(): void
    {
        Notification::fake();
        $product = $this->product(50);

        $this->actingAs($this->manager)->postJson('/api/v1/orders', [
            'customer_id' => Customer::factory()->create()->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'assigned_to' => $this->staff->id,
        ])->assertCreated();

        Notification::assertSentTo($this->staff, OrderAssignedNotification::class);
    }

    public function test_confirming_notifies_assignee_and_creator_but_not_the_actor(): void
    {
        Notification::fake();
        $order = $this->pendingOrder($this->product(50), 2, $this->staff);

        $this->actingAs($this->admin)->postJson("/api/v1/orders/{$order->id}/status", ['status' => 'confirmed'])->assertOk();

        Notification::assertSentTo([$this->staff, $this->manager], OrderStatusChangedNotification::class, function ($notification) use ($order) {
            return $notification->toArray($this->staff)['message'] === "Order {$order->order_number} has been confirmed.";
        });
        Notification::assertNotSentTo($this->admin, OrderStatusChangedNotification::class);
    }

    public function test_minor_status_steps_send_nothing(): void
    {
        $order = $this->pendingOrder($this->product(50), 1, $this->staff);
        $this->actingAs($this->manager)->postJson("/api/v1/orders/{$order->id}/status", ['status' => 'confirmed'])->assertOk();
        Notification::fake();

        $this->actingAs($this->manager)->postJson("/api/v1/orders/{$order->id}/status", ['status' => 'processing'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_low_stock_alerts_admins_once_when_the_threshold_is_crossed(): void
    {
        Notification::fake();
        $product = $this->product(12);

        // 12 → 9: crosses the threshold of 10.
        $this->actingAs($this->manager)->postJson('/api/v1/orders/'.$this->pendingOrder($product, 3)->id.'/status', ['status' => 'confirmed'])->assertOk();
        // 9 → 8: already low, no second alert.
        $this->actingAs($this->manager)->postJson('/api/v1/orders/'.$this->pendingOrder($product, 1)->id.'/status', ['status' => 'confirmed'])->assertOk();

        Notification::assertSentToTimes($this->admin, LowStockNotification::class, 1);
        Notification::assertNotSentTo($this->staff, LowStockNotification::class);
        Notification::assertSentTo($this->admin, LowStockNotification::class, fn ($n) => $n->toArray($this->admin)['message'] === 'Low stock: Wireless Keyboard has 9 left.');
    }

    public function test_a_rolled_back_change_notifies_nobody(): void
    {
        Notification::fake();
        $plenty = $this->product(50);
        $scarce = $this->product(1);
        // Lower id first: plenty would be deducted (and cross nothing), then scarce fails and everything rolls back.
        $order = Order::factory()->create(['status' => OrderStatus::Pending, 'assigned_to' => $this->staff->id, 'created_by' => $this->manager->id]);
        OrderItem::factory()->for($order)->for($plenty)->create(['quantity' => 45]); // would cross the threshold
        OrderItem::factory()->for($order)->for($scarce)->create(['quantity' => 5]);

        $this->actingAs($this->admin)->postJson("/api/v1/orders/{$order->id}/status", ['status' => 'confirmed'])->assertUnprocessable();

        Notification::assertNothingSent();
    }

    public function test_notifications_api_lists_counts_and_marks_read(): void
    {
        $orders = Order::factory()->count(3)->create();
        foreach ($orders as $order) {
            $this->staff->notify(new OrderAssignedNotification($order));
        }
        $this->manager->notify(new OrderAssignedNotification($orders[0]));

        $this->actingAs($this->staff)->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.count', 3);

        $list = $this->actingAs($this->staff)->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.kind', 'order_assigned')
            ->assertJsonPath('data.0.read', false)
            ->json('data');

        $this->actingAs($this->staff)->postJson("/api/v1/notifications/{$list[0]['id']}/read")->assertOk()->assertJsonPath('data.read', true);
        $this->actingAs($this->staff)->getJson('/api/v1/notifications?unread=1')->assertJsonPath('meta.total', 2);

        // Someone else's notification is not found.
        $othersId = $this->manager->notifications()->first()->id;
        $this->actingAs($this->staff)->postJson("/api/v1/notifications/{$othersId}/read")->assertNotFound();

        $this->actingAs($this->staff)->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('data.marked', 2);
        $this->actingAs($this->staff)->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.count', 0);
        $this->assertNull($this->manager->notifications()->first()->read_at);
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }
}
