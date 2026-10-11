<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->manager = User::factory()->create()->assignRole('manager');
    }

    private function order(string $status, int $total, string $date, ?User $assignee = null): Order
    {
        return Order::factory()->create(['status' => $status, 'grand_total' => $total, 'order_date' => $date, 'assigned_to' => $assignee?->id]);
    }

    private function seedOrders(): void
    {
        $this->order('draft', 1000, '2026-09-01');
        $this->order('pending', 2000, '2026-09-01');
        $this->order('confirmed', 10000, '2026-09-01');
        $this->order('processing', 20000, '2026-09-03');
        $this->order('completed', 30001, '2026-09-10');
        $this->order('cancelled', 50000, '2026-09-10');
        $this->order('completed', 99999, '2026-08-31'); // outside the range
        $this->order('completed', 77777, '2026-09-05')->delete(); // deleted orders don't count
    }

    public function test_summary_counts_and_booked_revenue(): void
    {
        $this->seedOrders();

        $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/summary?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.period', ['from' => '2026-09-01', 'to' => '2026-09-30'])
            ->assertJsonPath('data.total_orders', 6)
            ->assertJsonPath('data.orders_by_status', [
                'draft' => 1, 'pending' => 1, 'confirmed' => 1, 'processing' => 1, 'ready' => 0, 'completed' => 1, 'cancelled' => 1,
            ])
            // Booked = confirmed + processing + completed; draft, pending and cancelled excluded.
            ->assertJsonPath('data.booked_orders', 3)
            ->assertJsonPath('data.revenue', 60001)
            ->assertJsonPath('data.revenue_formatted', '600.01')
            // 60001 / 3 = 20000.33 → 20000
            ->assertJsonPath('data.average_order_value', 20000)
            ->assertJsonPath('data.total_customers', Customer::count())
            ->assertJsonPath('data.total_products', Product::count());
    }

    public function test_revenue_trend_by_day_week_and_month_fills_gaps(): void
    {
        $this->seedOrders();

        $days = $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/revenue-trend?period=day&from=2026-09-01&to=2026-09-10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->json('data');
        $this->assertSame(['2026-09-01', 10000, 1], [$days[0]['period_start'], $days[0]['revenue'], $days[0]['orders']]);
        $this->assertSame(0, $days[1]['revenue']); // 2 Sep: no orders, still present
        $this->assertSame(20000, $days[2]['revenue']);

        // 2026-09-01 is a Tuesday: weeks start Monday 31 Aug and Monday 7 Sep.
        $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/revenue-trend?period=week&from=2026-09-01&to=2026-09-13')
            ->assertJsonPath('data.0.period_start', '2026-08-31')
            ->assertJsonPath('data.0.revenue', 30000)
            ->assertJsonPath('data.1.period_start', '2026-09-07')
            ->assertJsonPath('data.1.revenue', 30001)
            ->assertJsonPath('data.1.label', 'Week of 07 Sep');

        $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/revenue-trend?period=month&from=2026-08-01&to=2026-09-30')
            ->assertJsonPath('data.0.label', 'Aug 2026')
            ->assertJsonPath('data.0.revenue', 99999)
            ->assertJsonPath('data.1.revenue', 60001);
    }

    public function test_status_distribution_lists_every_status(): void
    {
        $this->seedOrders();

        $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/status-distribution?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonPath('data.4', ['value' => 'ready', 'label' => 'Ready', 'count' => 0])
            ->assertJsonPath('data.5', ['value' => 'completed', 'label' => 'Completed', 'count' => 1]);
    }

    public function test_results_are_cached_and_refreshed_after_a_change(): void
    {
        $order = $this->order('confirmed', 10000, now()->toDateString());
        $url = '/api/v1/dashboard/summary';

        $this->actingAs($this->manager)->getJson($url)->assertJsonPath('data.revenue', 10000);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->manager)->getJson($url)->assertJsonPath('data.revenue', 10000);
        $orderQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '`orders`'));
        DB::disableQueryLog();
        $this->assertCount(0, $orderQueries, 'The second call must come from the cache');

        // Any order change bumps the cache version.
        $order->forceFill(['status' => OrderStatus::Cancelled])->save();

        $this->actingAs($this->manager)->getJson($url)->assertJsonPath('data.revenue', 0);
    }

    public function test_staff_cannot_see_the_dashboard_but_gets_their_own_work(): void
    {
        $staff = User::factory()->create()->assignRole('staff');
        $this->order('pending', 100, '2026-09-01', $staff);
        $this->order('processing', 100, '2026-09-01', $staff);
        $this->order('completed', 100, '2026-09-01', $staff);
        $this->order('pending', 100, '2026-09-01'); // someone else's

        foreach (['summary', 'revenue-trend', 'status-distribution'] as $endpoint) {
            $this->actingAs($staff)->getJson("/api/v1/dashboard/{$endpoint}")->assertForbidden();
        }

        $this->actingAs($staff)
            ->getJson('/api/v1/dashboard/my-work')
            ->assertOk()
            ->assertJsonPath('data.open_orders', 2)
            ->assertJsonPath('data.orders_by_status.completed', 1);
    }

    public function test_dashboard_index_covers_the_soft_delete_filter(): void
    {
        // Every dashboard query filters deleted_at IS NULL; without it in the index MySQL reads the table per row.
        $columns = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'orders')
            ->where('INDEX_NAME', 'idx_orders_date_status_total')
            ->orderBy('SEQ_IN_INDEX')
            ->pluck('COLUMN_NAME')
            ->all();

        $this->assertSame(['order_date', 'status', 'grand_total', 'deleted_at'], $columns);
    }

    public function test_invalid_ranges_are_rejected(): void
    {
        $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/summary?from=2026-10-10&to=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);

        $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/revenue-trend?from=2024-01-01&to=2026-01-01&period=hour')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period']);

        $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/summary?from=2024-01-01&to=2026-01-01')
            ->assertUnprocessable()
            ->assertJsonPath('errors.to.0', 'The date range can be at most 366 days.');
    }
}
