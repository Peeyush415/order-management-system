<?php

namespace Tests\Feature;

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_number_is_derived_from_the_id(): void
    {
        $order = Order::factory()->create();

        $this->assertSame('ORD-'.(10000 + $order->id), $order->fresh()->order_number);
        $this->assertSame('ORD-10005', Order::numberFor(5));
    }

    public function test_relations_and_enum_casts(): void
    {
        $staff = User::factory()->create();
        $order = Order::factory()->draft()->assignedTo($staff)->create();
        OrderItem::factory()->count(2)->for($order)->create();

        $order = $order->fresh(['customer', 'assignee', 'creator', 'items.product']);

        $this->assertSame(OrderStatus::Draft, $order->status);
        $this->assertSame(OrderPriority::Normal, $order->priority);
        $this->assertTrue($order->assignee->is($staff));
        $this->assertNotNull($order->customer);
        $this->assertNotNull($order->creator);
        $this->assertCount(2, $order->items);
        $this->assertTrue($order->customer->orders->contains($order));
    }

    public function test_item_snapshots_the_product_at_order_time(): void
    {
        $product = Product::factory()->create(['name' => 'Old Name', 'price' => 1000, 'tax_rate_bps' => 1000]);
        $item = OrderItem::factory()->for($product)->create(['quantity' => 3]);

        $product->update(['name' => 'New Name', 'price' => 9999]);

        $item->refresh();
        $this->assertSame('Old Name', $item->product_name);
        $this->assertSame(1000, $item->unit_price);
        $this->assertSame(3000, $item->line_subtotal);
        $this->assertSame(300, $item->tax_amount);
        $this->assertSame(3300, $item->line_total);
    }

    public function test_removing_an_order_removes_its_items(): void
    {
        $item = OrderItem::factory()->create();

        $item->order->forceDelete();

        $this->assertDatabaseMissing('order_items', ['id' => $item->id]);
    }

    public function test_customer_with_orders_cannot_be_hard_deleted(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->for($customer)->create();

        $this->expectException(QueryException::class);

        $customer->forceDelete();
    }
}
