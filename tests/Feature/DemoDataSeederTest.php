<?php

namespace Tests\Feature;

use App\Models\Order;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Seeds once and checks the demo data is internally consistent.
 */
class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_is_complete_and_consistent(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Counts
        $this->assertDatabaseCount('customers', 100);
        $this->assertDatabaseCount('products', 50);
        $this->assertDatabaseCount('orders', 600);
        $this->assertSame(0, DB::table('orders')->whereNull('order_number')->count());
        $this->assertSame(0, DB::table('orders as o')->whereNotExists(fn ($q) => $q->from('order_items')->whereColumn('order_id', 'o.id'))->count(), 'Every order has items');

        // Order totals match their items, and the grand total formula holds
        $mismatched = DB::table('orders as o')
            ->joinSub(
                DB::table('order_items')->groupBy('order_id')->selectRaw('order_id, SUM(line_subtotal) s, SUM(discount_amount) d, SUM(tax_amount) t, SUM(line_total) g'),
                'i', 'i.order_id', '=', 'o.id'
            )
            ->where(fn ($q) => $q->whereColumn('o.subtotal', '!=', 'i.s')
                ->orWhereColumn('o.discount_total', '!=', 'i.d')
                ->orWhereColumn('o.tax_total', '!=', 'i.t')
                ->orWhereColumn('o.grand_total', '!=', 'i.g')
                ->orWhereRaw('o.grand_total != o.subtotal - o.discount_total + o.tax_total'))
            ->count();
        $this->assertSame(0, $mismatched, 'Order totals must match their items');

        // The last history row is the order's current status, and the first has no from_status
        $wrongLast = DB::table('orders as o')
            ->join('order_status_histories as h', 'h.order_id', '=', 'o.id')
            ->whereRaw('h.id = (SELECT MAX(id) FROM order_status_histories WHERE order_id = o.id)')
            ->whereColumn('h.to_status', '!=', 'o.status')
            ->count();
        $this->assertSame(0, $wrongLast, 'History must end at the current status');
        $this->assertSame(600, DB::table('order_status_histories')->whereNull('from_status')->count());

        // Stock: deducted exactly for orders that reached confirmed; product stock equals its ledger
        $this->assertSame(
            DB::table('orders')->whereNotNull('confirmed_at')->count(),
            DB::table('orders')->whereNotNull('stock_deducted_at')->count(),
        );
        $ledgerMismatch = DB::table('products as p')
            ->whereRaw('p.stock_quantity != (SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE product_id = p.id)')
            ->count();
        $this->assertSame(0, $ledgerMismatch, 'Product stock must equal the sum of its ledger');
        $this->assertSame(0, DB::table('stock_movements')->where('balance_after', '<', 0)->count());
        $this->assertGreaterThanOrEqual(4, DB::table('products')->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count());

        // Staff-moved orders are assigned; cancelled orders have a reason
        $this->assertSame(0, Order::whereNotNull('confirmed_at')->whereNull('assigned_to')->count());
        $this->assertSame(0, Order::where('status', 'cancelled')->whereNull('cancellation_reason')->count());
    }
}
