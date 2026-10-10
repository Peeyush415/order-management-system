<?php

namespace Tests\Feature\Api;

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderListTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->manager = User::factory()->create()->assignRole('manager');
        $this->staff = User::factory()->create(['name' => 'Sunita Rao'])->assignRole('staff');
    }

    private function ids($response): array
    {
        return collect($response->json('data'))->pluck('id')->sort()->values()->all();
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
    }

    public function test_manager_sees_all_orders_with_pagination(): void
    {
        Order::factory()->count(3)->create();
        Order::factory()->assignedTo($this->staff)->create();

        $this->actingAs($this->manager)
            ->getJson('/api/v1/orders?per_page=3')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonStructure(['data' => [[
                'id', 'order_number', 'status' => ['value', 'label'], 'priority' => ['value', 'label'],
                'order_date', 'customer' => ['id', 'name', 'email'], 'assignee', 'grand_total', 'grand_total_formatted',
            ]]]);
    }

    public function test_staff_sees_only_orders_assigned_to_them(): void
    {
        $mine = Order::factory()->count(2)->assignedTo($this->staff)->create();
        Order::factory()->count(3)->create();
        Order::factory()->assignedTo(User::factory()->create()->assignRole('staff'))->create();

        $response = $this->actingAs($this->staff)->getJson('/api/v1/orders')->assertOk();

        $this->assertSame($mine->pluck('id')->sort()->values()->all(), $this->ids($response));

        // Filters can't widen the scope.
        $this->actingAs($this->staff)
            ->getJson('/api/v1/orders?assigned_to='.$this->manager->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_filters_by_status_priority_customer_assignee_and_dates(): void
    {
        $customer = Customer::factory()->create();
        $match = Order::factory()->for($customer)->assignedTo($this->staff)->create([
            'status' => OrderStatus::Confirmed, 'priority' => OrderPriority::Urgent, 'order_date' => '2026-03-15',
        ]);
        Order::factory()->for($customer)->assignedTo($this->staff)->create(['status' => OrderStatus::Draft, 'priority' => OrderPriority::Urgent, 'order_date' => '2026-03-15']);
        Order::factory()->for($customer)->assignedTo($this->staff)->create(['status' => OrderStatus::Confirmed, 'priority' => OrderPriority::Low, 'order_date' => '2026-03-15']);
        Order::factory()->for($customer)->assignedTo($this->staff)->create(['status' => OrderStatus::Confirmed, 'priority' => OrderPriority::Urgent, 'order_date' => '2026-05-01']);
        Order::factory()->assignedTo($this->staff)->create(['status' => OrderStatus::Confirmed, 'priority' => OrderPriority::Urgent, 'order_date' => '2026-03-15']);

        $query = http_build_query([
            'status' => ['confirmed', 'processing'],
            'priority' => [4],
            'customer_id' => $customer->id,
            'assigned_to' => $this->staff->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]);

        $response = $this->actingAs($this->manager)->getJson("/api/v1/orders?{$query}")->assertOk();

        $this->assertSame([$match->id], $this->ids($response));
    }

    public function test_sorts_by_total_customer_name_and_workflow_status(): void
    {
        $zed = Order::factory()->for(Customer::factory()->create(['name' => 'Zed']))->create(['grand_total' => 100, 'status' => OrderStatus::Completed]);
        $amy = Order::factory()->for(Customer::factory()->create(['name' => 'Amy']))->create(['grand_total' => 900, 'status' => OrderStatus::Draft]);
        $max = Order::factory()->for(Customer::factory()->create(['name' => 'Max']))->create(['grand_total' => 500, 'status' => OrderStatus::Confirmed]);

        $order = fn (string $q) => collect($this->actingAs($this->manager)->getJson("/api/v1/orders?{$q}")->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$zed->id, $max->id, $amy->id], $order('sort=grand_total&direction=asc'));
        $this->assertSame([$amy->id, $max->id, $zed->id], $order('sort=customer&direction=asc'));
        // draft → confirmed → completed, not alphabetical.
        $this->assertSame([$amy->id, $max->id, $zed->id], $order('sort=status&direction=asc'));
    }

    public function test_search_by_order_number_with_or_without_prefix(): void
    {
        $target = Order::factory()->create();
        Order::factory()->count(2)->create();
        $number = Order::numberFor($target->id);

        foreach ([$number, strtolower($number), (string) (10000 + $target->id)] as $term) {
            $response = $this->actingAs($this->manager)->getJson('/api/v1/orders?search='.urlencode($term))->assertOk();
            $this->assertSame([$target->id], $this->ids($response), "search={$term}");
        }
    }

    public function test_search_by_assigned_employee_name(): void
    {
        $assigned = Order::factory()->assignedTo($this->staff)->create();
        Order::factory()->count(2)->create();

        $response = $this->actingAs($this->manager)->getJson('/api/v1/orders?search=Sunita')->assertOk();

        $this->assertSame([$assigned->id], $this->ids($response));
    }

    public function test_invalid_parameters_are_rejected(): void
    {
        $this->actingAs($this->manager)
            ->getJson('/api/v1/orders?sort=customer_id&per_page=101&status[]=shipped&priority[]=9&date_from=2026-05-01&date_to=2026-04-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'per_page', 'status.0', 'priority.0', 'date_to']);
    }

    public function test_query_count_does_not_grow_with_the_number_of_orders(): void
    {
        // Warm-up: the first request also loads and caches roles/permissions.
        $this->actingAs($this->manager)->getJson('/api/v1/orders')->assertOk();

        Order::factory()->count(5)->assignedTo($this->staff)->create();
        $few = $this->countQueries(fn () => $this->actingAs($this->manager)->getJson('/api/v1/orders?per_page=100')->assertOk());

        Order::factory()->count(20)->assignedTo($this->staff)->create();
        $many = $this->countQueries(fn () => $this->actingAs($this->manager)->getJson('/api/v1/orders?per_page=100')->assertOk());

        $this->assertSame($few, $many, 'Listing must not run one query per order (N+1)');
    }

    private function countQueries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
