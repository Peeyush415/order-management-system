<?php

namespace Tests\Feature\Api;

use App\Enums\OrderActivityType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAssignTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->manager = User::factory()->create()->assignRole('manager');
        $this->staff = User::factory()->create(['name' => 'Staff One'])->assignRole('staff');
    }

    private function assign(User $user, Order $order, ?int $assigneeId)
    {
        return $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/assign", ['assigned_to' => $assigneeId]);
    }

    public function test_manager_assigns_and_unassigns(): void
    {
        $order = Order::factory()->create()->fresh();

        $this->assign($this->manager, $order, $this->staff->id)
            ->assertOk()
            ->assertJsonPath('message', 'Order assigned to Staff One')
            ->assertJsonPath('data.assignee.id', $this->staff->id)
            ->assertJsonPath('data.version', 2);

        $this->assertDatabaseHas('order_activities', [
            'order_id' => $order->id, 'type' => OrderActivityType::Assigned->value, 'description' => 'Assigned to Staff One',
        ]);

        $this->assign($this->manager, $order, null)
            ->assertOk()
            ->assertJsonPath('message', 'Order unassigned')
            ->assertJsonPath('data.assignee', null);
    }

    public function test_assignee_must_be_an_active_manager_or_staff(): void
    {
        $order = Order::factory()->create();
        $admin = User::factory()->create()->assignRole('admin');
        $inactive = User::factory()->inactive()->create()->assignRole('staff');

        foreach ([$admin->id, $inactive->id, 99999] as $id) {
            $this->assign($this->manager, $order, $id)
                ->assertUnprocessable()
                ->assertJsonPath('errors.assigned_to.0', 'The assignee must be an active manager or staff member.');
        }

        $this->actingAs($this->manager)->postJson("/api/v1/orders/{$order->id}/assign", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to']);
    }

    public function test_staff_cannot_assign(): void
    {
        $order = Order::factory()->assignedTo($this->staff)->create();

        $this->assign($this->staff, $order, $this->staff->id)->assertForbidden();
    }

    public function test_finished_orders_cannot_be_reassigned(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Completed]);

        $this->assign($this->manager, $order, $this->staff->id)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Completed or cancelled orders cannot be reassigned.');
    }
}
