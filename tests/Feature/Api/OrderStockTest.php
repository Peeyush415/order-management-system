<?php

namespace Tests\Feature\Api;

use App\Enums\OrderActivityType;
use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStockTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->manager = User::factory()->create()->assignRole('manager');
    }

    /** A product whose stock is backed by an opening ledger row, like real data. */
    private function productWithStock(int $stock, string $name = 'Wireless Keyboard'): Product
    {
        $product = Product::factory()->create(['name' => $name, 'stock_quantity' => $stock]);
        StockMovement::create([
            'product_id' => $product->id, 'type' => StockMovementType::ManualAdjustment,
            'quantity' => $stock, 'balance_after' => $stock, 'reason' => 'Opening stock',
        ]);

        return $product;
    }

    /** @param  array<int, int>  $lines  product id => quantity */
    private function orderWith(array $lines, OrderStatus $status = OrderStatus::Pending): Order
    {
        $order = Order::factory()->create(['status' => $status]);
        foreach ($lines as $productId => $quantity) {
            OrderItem::factory()->for($order)->create(['product_id' => $productId, 'quantity' => $quantity]);
        }

        return $order->fresh();
    }

    private function changeStatus(Order $order, string $status, ?string $remarks = null)
    {
        return $this->actingAs($this->manager)->postJson("/api/v1/orders/{$order->id}/status", array_filter(['status' => $status, 'remarks' => $remarks]));
    }

    private function assertStockMatchesLedger(Product ...$products): void
    {
        foreach ($products as $product) {
            $this->assertSame($product->fresh()->stock_quantity, (int) StockMovement::where('product_id', $product->id)->sum('quantity'), "Ledger of {$product->name}");
        }
    }

    public function test_confirming_deducts_stock_and_writes_the_ledger(): void
    {
        // The assessment example: available 100, order 5 → 95.
        $keyboard = $this->productWithStock(100);
        $mouse = $this->productWithStock(20, 'Mouse');
        $order = $this->orderWith([$keyboard->id => 5, $mouse->id => 2]);

        $this->changeStatus($order, 'confirmed')->assertOk()->assertJsonPath('data.status.value', 'confirmed');

        $this->assertSame(95, $keyboard->fresh()->stock_quantity);
        $this->assertSame(18, $mouse->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $keyboard->id, 'order_id' => $order->id, 'user_id' => $this->manager->id,
            'type' => StockMovementType::OrderDeduct->value, 'quantity' => -5, 'balance_after' => 95,
        ]);
        $this->assertNotNull($order->fresh()->stock_deducted_at);
        $this->assertDatabaseHas('order_activities', ['order_id' => $order->id, 'type' => OrderActivityType::StockDeducted->value]);
        $this->assertStockMatchesLedger($keyboard, $mouse);
    }

    public function test_insufficient_stock_rejects_the_confirmation_and_changes_nothing(): void
    {
        $plenty = $this->productWithStock(50, 'Plenty');
        $scarce = $this->productWithStock(3, 'Scarce Lamp');
        // The plentiful product has the lower id, so it is deducted first and must be rolled back.
        $order = $this->orderWith([$plenty->id => 10, $scarce->id => 5]);

        $this->changeStatus($order, 'confirmed')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Insufficient stock for Scarce Lamp: 3 available, 5 requested.');

        $this->assertSame(50, $plenty->fresh()->stock_quantity);
        $this->assertSame(3, $scarce->fresh()->stock_quantity);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertNull($order->fresh()->stock_deducted_at);
        $this->assertSame(0, StockMovement::where('order_id', $order->id)->count());
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    public function test_the_last_units_cannot_be_sold_twice(): void
    {
        $product = $this->productWithStock(10);
        $first = $this->orderWith([$product->id => 6]);
        $second = $this->orderWith([$product->id => 6]);

        $this->changeStatus($first, 'confirmed')->assertOk();
        $this->changeStatus($second, 'confirmed')->assertUnprocessable();

        $this->assertSame(4, $product->fresh()->stock_quantity);
        $this->assertStockMatchesLedger($product);
    }

    public function test_cancelling_a_confirmed_order_returns_the_stock(): void
    {
        $product = $this->productWithStock(30);
        $order = $this->orderWith([$product->id => 7]);
        $this->changeStatus($order, 'confirmed')->assertOk();
        $this->changeStatus($order, 'processing')->assertOk();

        $this->changeStatus($order, 'cancelled', 'Customer cancelled')->assertOk();

        $this->assertSame(30, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'order_id' => $order->id,
            'type' => StockMovementType::OrderRestock->value, 'quantity' => 7, 'balance_after' => 30,
        ]);
        $this->assertDatabaseHas('order_activities', ['order_id' => $order->id, 'type' => OrderActivityType::Restocked->value]);
        $this->assertStockMatchesLedger($product);
    }

    public function test_cancelling_a_pending_order_does_not_touch_stock(): void
    {
        $product = $this->productWithStock(30);
        $order = $this->orderWith([$product->id => 7]);

        $this->changeStatus($order, 'cancelled', 'Changed mind')->assertOk();

        $this->assertSame(30, $product->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::where('order_id', $order->id)->count());
    }

    public function test_later_steps_do_not_move_stock_again(): void
    {
        $product = $this->productWithStock(30);
        $order = $this->orderWith([$product->id => 7]);

        foreach (['confirmed', 'processing', 'ready', 'completed'] as $status) {
            $this->changeStatus($order, $status)->assertOk();
        }

        $this->assertSame(23, $product->fresh()->stock_quantity);
        $this->assertSame(1, StockMovement::where('order_id', $order->id)->count());
    }
}
