<?php

namespace Tests\Feature\Api;

use App\Enums\OrderPriority;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['name' => 'Admin One'])->assignRole('admin');
    }

    public function test_only_audit_view_can_read_the_log(): void
    {
        $this->getJson('/api/v1/audit-logs')->assertUnauthorized();
        $this->actingAs(User::factory()->create()->assignRole('manager'))->getJson('/api/v1/audit-logs')->assertForbidden();
        $this->actingAs($this->admin)->getJson('/api/v1/audit-logs')->assertOk();
    }

    public function test_an_order_change_reads_like_the_assessment_example(): void
    {
        $john = User::factory()->create(['name' => 'John'])->assignRole('staff');
        $david = User::factory()->create(['name' => 'David'])->assignRole('staff');
        $order = Order::factory()->create(['priority' => OrderPriority::Normal, 'assigned_to' => $john->id]);

        $this->actingAs($this->admin);
        $order->update(['priority' => OrderPriority::High, 'assigned_to' => $david->id]);

        $row = $this->getJson('/api/v1/audit-logs?auditable_type=order&auditable_id='.$order->id.'&event=assignment_changed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data.0');

        $this->assertSame('Admin One', $row['user']['name']);
        $this->assertSame('Assignment changed', $row['event_label']);
        $this->assertSame(['type' => 'order', 'type_label' => 'Order', 'id' => $order->id, 'label' => $order->order_number], $row['subject']);
        $changes = collect($row['changes'])->keyBy('field');
        $this->assertSame(['field' => 'priority', 'label' => 'Priority', 'old' => 'Normal', 'new' => 'High'], $changes['priority']);
        $this->assertSame(['field' => 'assigned_to', 'label' => 'Assigned to', 'old' => 'John', 'new' => 'David'], $changes['assigned_to']);
    }

    public function test_filters_by_user_event_type_and_date(): void
    {
        $this->actingAs($this->admin);
        $customer = Customer::factory()->create();
        $customer->update(['city' => 'Mumbai']);
        AuditLog::query()->update(['created_at' => '2026-01-10 12:00:00']);
        Customer::factory()->create();

        $base = '/api/v1/audit-logs?auditable_type=customer';
        $this->getJson($base)->assertJsonCount(3, 'data');
        $this->getJson("{$base}&event=updated")->assertJsonCount(1, 'data')->assertJsonPath('data.0.changes.0.new', 'Mumbai');
        $this->getJson("{$base}&from=2026-01-10&to=2026-01-10")->assertJsonCount(2, 'data');
        $this->getJson("{$base}&user_id={$this->admin->id}")->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/audit-logs?user_id=999999')->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/audit-logs?auditable_type=planet')->assertJsonValidationErrors(['auditable_type']);
        $this->getJson('/api/v1/audit-logs?auditable_id=5')->assertJsonValidationErrors(['auditable_type']);
        $this->getJson('/api/v1/audit-logs?from=2026-02-01&to=2026-01-01')->assertJsonValidationErrors(['to']);
    }

    public function test_a_deleted_order_keeps_its_label_and_money_is_formatted(): void
    {
        $this->actingAs($this->admin);
        $order = Order::factory()->create(['grand_total' => 36300]);
        $order->forceFill(['grand_total' => 40000])->save();
        $order->delete();

        $rows = collect($this->getJson('/api/v1/audit-logs?auditable_type=order')->assertOk()->json('data'));

        $this->assertSame($order->order_number, $rows->firstWhere('event', 'deleted')['subject']['label']);
        $total = $rows->where('event', 'updated')->flatMap(fn (array $row) => $row['changes'])->firstWhere('field', 'grand_total');
        $this->assertSame(['363.00', '400.00'], [$total['old'], $total['new']]);
    }

    public function test_query_count_does_not_grow_with_the_page(): void
    {
        $this->actingAs($this->admin);
        $staff = User::factory()->create()->assignRole('staff');
        Order::factory()->count(3)->create(['assigned_to' => $staff->id]);
        $this->getJson('/api/v1/audit-logs'); // warms the permission cache

        // Same page size, every row on it: only the number of rows changes.
        DB::enableQueryLog();
        $this->getJson('/api/v1/audit-logs?per_page=100')->assertOk();
        $few = count(DB::getQueryLog());

        Order::factory()->count(10)->create(['assigned_to' => $staff->id]);
        DB::flushQueryLog();
        $this->getJson('/api/v1/audit-logs?per_page=100')->assertOk();

        $this->assertSame($few, count(DB::getQueryLog()));
    }
}
