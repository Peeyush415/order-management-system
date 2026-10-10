<?php

namespace Tests\Feature\Api;

use App\Enums\OrderActivityType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderShowUpdateDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    private User $staff;

    private Product $productA;

    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create()->assignRole('admin');
        $this->manager = User::factory()->create()->assignRole('manager');
        $this->staff = User::factory()->create()->assignRole('staff');
        $this->productA = Product::factory()->create(['price' => 10000, 'tax_rate_bps' => 1000]);
        $this->productB = Product::factory()->create(['price' => 5000, 'tax_rate_bps' => 1000]);
    }

    private function orderWithItem(array $attributes = []): Order
    {
        $order = Order::factory()->create($attributes);
        OrderItem::factory()->for($order)->for($this->productA)->create(['quantity' => 1]);

        // fresh() picks up database defaults such as version = 1.
        return $order->fresh();
    }

    private function updatePayload(Order $order, array $overrides = []): array
    {
        return [
            'customer_id' => $order->customer_id,
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 2],
                ['product_id' => $this->productB->id, 'quantity' => 3],
            ],
            'discount_type' => 'fixed',
            'discount_value' => 2000,
            'priority' => 4,
            'order_date' => '2026-10-01',
            'version' => $order->version,
            ...$overrides,
        ];
    }

    // --- show ---

    public function test_manager_sees_the_full_order(): void
    {
        $order = $this->orderWithItem(['assigned_to' => $this->staff->id]);

        $this->actingAs($this->manager)
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.assignee.id', $this->staff->id)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonStructure(['data' => ['customer' => ['name', 'email', 'phone', 'address' => ['line1', 'city', 'country']], 'version']]);
    }

    public function test_staff_sees_only_their_assigned_order(): void
    {
        $mine = $this->orderWithItem(['assigned_to' => $this->staff->id]);
        $notMine = $this->orderWithItem();

        $this->actingAs($this->staff)->getJson("/api/v1/orders/{$mine->id}")->assertOk();
        $this->actingAs($this->staff)->getJson("/api/v1/orders/{$notMine->id}")->assertForbidden();
    }

    public function test_missing_or_deleted_order_is_404(): void
    {
        $order = $this->orderWithItem();
        $order->delete();

        $this->actingAs($this->manager)->getJson('/api/v1/orders/99999')->assertNotFound();
        $this->actingAs($this->manager)->getJson("/api/v1/orders/{$order->id}")->assertNotFound();
    }

    // --- update ---

    public function test_pending_order_is_fully_updated_and_repriced(): void
    {
        $order = $this->orderWithItem(['status' => OrderStatus::Pending]);

        $this->actingAs($this->manager)
            ->putJson("/api/v1/orders/{$order->id}", $this->updatePayload($order, ['grand_total' => 1]))
            ->assertOk()
            ->assertJsonPath('message', 'Order updated successfully')
            ->assertJsonPath('data.grand_total', 36300)
            ->assertJsonPath('data.priority.value', 4)
            ->assertJsonPath('data.order_date', '2026-10-01')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.version', 2);

        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseHas('order_activities', ['order_id' => $order->id, 'type' => OrderActivityType::Updated->value, 'user_id' => $this->manager->id]);
    }

    public function test_a_stale_version_gets_409_and_changes_nothing(): void
    {
        $order = $this->orderWithItem(['status' => OrderStatus::Pending]);
        $loadedVersion = $order->version;

        // First user saves.
        $this->actingAs($this->manager)
            ->putJson("/api/v1/orders/{$order->id}", $this->updatePayload($order, ['priority' => 3]))
            ->assertOk();

        // Second user still has the version they loaded earlier.
        $this->actingAs($this->admin)
            ->putJson("/api/v1/orders/{$order->id}", $this->updatePayload($order, ['priority' => 1, 'version' => $loadedVersion]))
            ->assertStatus(409)
            ->assertJsonPath('message', 'This order was changed by someone else. Reload and try again.');

        $this->assertSame(OrderPriority::High, $order->fresh()->priority);
        $this->assertSame(2, $order->fresh()->version);
    }

    public function test_confirmed_order_can_only_change_priority(): void
    {
        $order = $this->orderWithItem(['status' => OrderStatus::Confirmed]);

        $this->actingAs($this->manager)
            ->putJson("/api/v1/orders/{$order->id}", $this->updatePayload($order))
            ->assertUnprocessable()
            ->assertJsonPath('errors.items.0', 'Only the priority can be changed once the order is confirmed.')
            ->assertJsonValidationErrors(['customer_id', 'discount_type', 'order_date']);

        $this->actingAs($this->manager)
            ->putJson("/api/v1/orders/{$order->id}", ['priority' => 4, 'version' => $order->version])
            ->assertOk()
            ->assertJsonPath('data.priority.value', 4)
            ->assertJsonPath('data.version', $order->version + 1);

        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_completed_order_cannot_be_edited(): void
    {
        $order = $this->orderWithItem(['status' => OrderStatus::Completed]);

        $this->actingAs($this->manager)
            ->putJson("/api/v1/orders/{$order->id}", ['priority' => 4, 'version' => $order->version])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Completed or cancelled orders cannot be edited.');
    }

    public function test_update_requires_version_and_rejects_status_or_assignee(): void
    {
        $order = $this->orderWithItem(['status' => OrderStatus::Draft]);

        $this->actingAs($this->manager)
            ->putJson("/api/v1/orders/{$order->id}", $this->updatePayload($order, ['version' => null, 'status' => 'completed', 'assigned_to' => $this->staff->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.version.0', 'The order version is required. Reload the order and try again.')
            ->assertJsonPath('errors.status.0', 'Use the status action to change the status.')
            ->assertJsonPath('errors.assigned_to.0', 'Use the assign action to change the assignee.');
    }

    public function test_staff_cannot_update(): void
    {
        $order = $this->orderWithItem(['assigned_to' => $this->staff->id]);

        $this->actingAs($this->staff)
            ->putJson("/api/v1/orders/{$order->id}", [])
            ->assertForbidden();
    }

    // --- delete ---

    public function test_admin_deletes_a_draft_order(): void
    {
        $order = $this->orderWithItem(['status' => OrderStatus::Draft]);

        $this->actingAs($this->admin)->deleteJson("/api/v1/orders/{$order->id}")->assertNoContent();

        $this->assertSoftDeleted($order);
    }

    public function test_confirmed_order_must_be_cancelled_before_delete(): void
    {
        $order = $this->orderWithItem(['status' => OrderStatus::Confirmed]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/orders/{$order->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Only draft, pending or cancelled orders can be deleted. Cancel the order first.');

        $this->assertNotSoftDeleted($order);
    }

    public function test_only_admin_may_delete(): void
    {
        $order = $this->orderWithItem(['status' => OrderStatus::Draft]);

        $this->actingAs($this->manager)->deleteJson("/api/v1/orders/{$order->id}")->assertForbidden();
        $this->actingAs($this->staff)->deleteJson("/api/v1/orders/{$order->id}")->assertForbidden();

        $this->assertNotSoftDeleted($order);
    }

    public function test_inactive_customer_cannot_be_set_on_update(): void
    {
        $order = $this->orderWithItem(['status' => OrderStatus::Pending]);
        $inactive = Customer::factory()->inactive()->create();

        $this->actingAs($this->manager)
            ->putJson("/api/v1/orders/{$order->id}", $this->updatePayload($order, ['customer_id' => $inactive->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }
}
