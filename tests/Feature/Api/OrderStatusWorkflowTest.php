<?php

namespace Tests\Feature\Api;

use App\Enums\OrderActivityType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusWorkflowTest extends TestCase
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
        $this->manager = User::factory()->create(['name' => 'Meera Manager'])->assignRole('manager');
        $this->staff = User::factory()->create()->assignRole('staff');
    }

    private function order(OrderStatus $status, ?User $assignee = null): Order
    {
        return Order::factory()->create(['status' => $status, 'assigned_to' => $assignee?->id])->fresh();
    }

    private function changeStatus(User $user, Order $order, string $status, ?string $remarks = null)
    {
        return $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/status", array_filter(['status' => $status, 'remarks' => $remarks]));
    }

    public function test_manager_confirms_a_pending_order_and_history_is_written(): void
    {
        $order = $this->order(OrderStatus::Pending);

        $this->changeStatus($this->manager, $order, 'confirmed', 'Customer confirmed the order.')
            ->assertOk()
            ->assertJsonPath('message', 'Order status changed to Confirmed')
            ->assertJsonPath('data.status.value', 'confirmed')
            ->assertJsonPath('data.version', 2);

        $order->refresh();
        $this->assertNotNull($order->confirmed_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id, 'from_status' => 'pending', 'to_status' => 'confirmed',
            'changed_by' => $this->manager->id, 'remarks' => 'Customer confirmed the order.',
        ]);
        $this->assertDatabaseHas('order_activities', ['order_id' => $order->id, 'type' => OrderActivityType::StatusChanged->value]);
    }

    public function test_full_happy_path_to_completed(): void
    {
        $order = $this->order(OrderStatus::Draft, $this->staff);

        foreach (['pending', 'confirmed', 'processing', 'ready', 'completed'] as $status) {
            $this->changeStatus($this->manager, $order, $status)->assertOk();
        }

        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertNotNull($order->completed_at);
        $this->assertSame(6, $order->version);
        $this->assertDatabaseCount('order_status_histories', 5);
    }

    public function test_invalid_transitions_from_the_assessment_are_rejected(): void
    {
        $completed = $this->order(OrderStatus::Completed);
        $cancelled = $this->order(OrderStatus::Cancelled);

        $this->changeStatus($this->admin, $completed, 'draft')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Invalid status transition is not allowed.')
            ->assertJsonPath('errors.status.0', 'Cannot change status from Completed to Draft.');

        $this->changeStatus($this->admin, $cancelled, 'processing')
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Cannot change status from Cancelled to Processing.');

        $this->assertSame(OrderStatus::Completed, $completed->fresh()->status);
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    public function test_skipping_a_step_is_rejected(): void
    {
        $order = $this->order(OrderStatus::Pending);

        $this->changeStatus($this->manager, $order, 'completed')->assertUnprocessable();
    }

    public function test_cancelling_needs_a_reason_which_is_stored(): void
    {
        $order = $this->order(OrderStatus::Pending);

        $this->changeStatus($this->manager, $order, 'cancelled')
            ->assertUnprocessable()
            ->assertJsonPath('errors.remarks.0', 'Please give a reason for cancelling the order.');

        $this->changeStatus($this->manager, $order, 'cancelled', 'Duplicate order')->assertOk();

        $order->refresh();
        $this->assertSame('Duplicate order', $order->cancellation_reason);
        $this->assertNotNull($order->cancelled_at);
    }

    public function test_staff_moves_their_own_order_forward_only(): void
    {
        $order = $this->order(OrderStatus::Confirmed, $this->staff);

        $this->changeStatus($this->staff, $order, 'processing')->assertOk();
        $this->changeStatus($this->staff, $order, 'ready')->assertOk();
        $this->changeStatus($this->staff, $order, 'cancelled', 'Not needed')->assertForbidden();

        $pending = $this->order(OrderStatus::Pending, $this->staff);
        $this->changeStatus($this->staff, $pending, 'confirmed')->assertForbidden();
    }

    public function test_staff_cannot_touch_orders_not_assigned_to_them(): void
    {
        $order = $this->order(OrderStatus::Confirmed);

        // 403 even with invalid input: the route checks the order before validation.
        $this->actingAs($this->staff)->postJson("/api/v1/orders/{$order->id}/status", [])->assertForbidden();
        $this->changeStatus($this->staff, $order, 'processing')->assertForbidden();
    }

    public function test_only_admin_may_cancel_a_ready_order(): void
    {
        $order = $this->order(OrderStatus::Ready);

        $this->changeStatus($this->manager, $order, 'cancelled', 'Late change')->assertForbidden();
        $this->changeStatus($this->admin, $order, 'cancelled', 'Late change')->assertOk();
    }

    public function test_second_simultaneous_confirm_is_rejected(): void
    {
        $order = $this->order(OrderStatus::Pending);

        $this->changeStatus($this->manager, $order, 'confirmed')->assertOk();
        // The second user still sees "pending" on their screen.
        $this->changeStatus($this->admin, $order, 'confirmed')
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Cannot change status from Confirmed to Confirmed.');

        $this->assertDatabaseCount('order_status_histories', 1);
    }

    public function test_allowed_transitions_depend_on_the_user(): void
    {
        $ready = $this->order(OrderStatus::Ready, $this->staff);
        $values = fn (User $user) => collect($this->actingAs($user)->getJson("/api/v1/orders/{$ready->id}/allowed-transitions")->assertOk()->json('data.transitions'))->pluck('value')->all();

        $this->assertSame(['completed', 'cancelled'], $values($this->admin));
        $this->assertSame(['completed'], $values($this->manager));
        $this->assertSame(['completed'], $values($this->staff));

        $this->actingAs($this->admin)
            ->getJson("/api/v1/orders/{$ready->id}/allowed-transitions")
            ->assertJsonPath('data.current.value', 'ready')
            ->assertJsonPath('data.transitions.1.requires_remarks', true);

        $this->actingAs($this->manager)
            ->getJson('/api/v1/orders/'.$this->order(OrderStatus::Completed)->id.'/allowed-transitions')
            ->assertJsonPath('data.transitions', []);
    }

    public function test_status_history_reads_as_a_timeline(): void
    {
        $order = $this->order(OrderStatus::Pending, $this->staff);
        $this->changeStatus($this->manager, $order, 'confirmed', 'Customer confirmed the order.');
        $this->changeStatus($this->staff, $order, 'processing');

        $this->actingAs($this->staff)
            ->getJson("/api/v1/orders/{$order->id}/status-history")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.from.label', 'Pending')
            ->assertJsonPath('data.0.to.label', 'Confirmed')
            ->assertJsonPath('data.0.changed_by.name', 'Meera Manager')
            ->assertJsonPath('data.0.remarks', 'Customer confirmed the order.')
            ->assertJsonPath('data.1.to.value', 'processing');

        $other = $this->order(OrderStatus::Pending);
        $this->actingAs($this->staff)->getJson("/api/v1/orders/{$other->id}/status-history")->assertForbidden();
    }
}
