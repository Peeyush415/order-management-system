<?php

namespace Tests\Feature\Api;

use App\Enums\OrderActivityType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OrderCreateTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Customer $customer;

    private Product $productA;

    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->manager = User::factory()->create()->assignRole('manager');
        $this->customer = Customer::factory()->create();
        // The assessment example: A = $100, B = $50, both 10% tax.
        $this->productA = Product::factory()->create(['name' => 'Product A', 'sku' => 'PROD-A', 'price' => 10000, 'tax_rate_bps' => 1000]);
        $this->productB = Product::factory()->create(['name' => 'Product B', 'sku' => 'PROD-B', 'price' => 5000, 'tax_rate_bps' => 1000]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'customer_id' => $this->customer->id,
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 2],
                ['product_id' => $this->productB->id, 'quantity' => 3],
            ],
            'discount_type' => 'fixed',
            'discount_value' => 2000,
            'priority' => 3,
            'order_date' => '2026-10-07',
            ...$overrides,
        ];
    }

    public function test_assessment_example_is_calculated_by_the_backend(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/api/v1/orders', $this->payload([
            // Tampered totals and prices from the client must be ignored.
            'subtotal' => 1,
            'grand_total' => 1,
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 2, 'unit_price' => 1],
                ['product_id' => $this->productB->id, 'quantity' => 3, 'unit_price' => 1],
            ],
        ]));

        $response->assertCreated()
            ->assertJsonPath('message', 'Order created successfully')
            ->assertJsonPath('data.subtotal', 35000)
            ->assertJsonPath('data.discount_total', 2000)
            ->assertJsonPath('data.tax_total', 3300)
            ->assertJsonPath('data.grand_total', 36300)
            ->assertJsonPath('data.formatted.grand_total', '363.00')
            ->assertJsonPath('data.status.value', 'pending')
            ->assertJsonPath('data.priority.label', 'High')
            ->assertJsonPath('data.customer.id', $this->customer->id)
            ->assertJsonPath('data.creator.id', $this->manager->id)
            ->assertJsonPath('data.assignee', null)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.unit_price', 10000)
            ->assertJsonPath('data.version', 1);

        $order = Order::findOrFail($response->json('data.id'));
        $this->assertSame(Order::numberFor($order->id), $order->order_number);
        $this->assertSame($order->order_number, $response->json('data.order_number'));
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'grand_total' => 36300, 'created_by' => $this->manager->id]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_name' => 'Product A', 'sku' => 'PROD-A', 'unit_price' => 10000, 'discount_amount' => 1143, 'line_total' => 20743]);
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'from_status' => null, 'to_status' => 'pending', 'changed_by' => $this->manager->id]);
        $this->assertDatabaseHas('order_activities', ['order_id' => $order->id, 'type' => OrderActivityType::Created->value, 'user_id' => $this->manager->id]);
    }

    public function test_stock_is_not_touched_at_creation(): void
    {
        $this->productA->forceFill(['stock_quantity' => 50])->save();

        $this->actingAs($this->manager)->postJson('/api/v1/orders', $this->payload())->assertCreated();

        $this->assertSame(50, $this->productA->fresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_can_save_as_draft_with_assignee_and_first_note(): void
    {
        $staff = User::factory()->create()->assignRole('staff');

        $response = $this->actingAs($this->manager)->postJson('/api/v1/orders', $this->payload([
            'status' => 'draft',
            'assigned_to' => $staff->id,
            'note' => 'Customer wants delivery before Friday.',
        ]))->assertCreated()
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonPath('data.assignee.id', $staff->id);

        $this->assertDatabaseHas('order_notes', ['order_id' => $response->json('data.id'), 'user_id' => $this->manager->id, 'body' => 'Customer wants delivery before Friday.']);
    }

    public function test_defaults_to_normal_priority_and_today(): void
    {
        $payload = collect($this->payload())->except(['priority', 'order_date', 'discount_type', 'discount_value'])->all();

        $this->actingAs($this->manager)->postJson('/api/v1/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.priority.value', 2)
            ->assertJsonPath('data.order_date', now()->toDateString())
            ->assertJsonPath('data.discount_total', 0)
            ->assertJsonPath('data.grand_total', 38500);
    }

    public function test_staff_cannot_create_orders(): void
    {
        $staff = User::factory()->create()->assignRole('staff');

        $this->actingAs($staff)->postJson('/api/v1/orders', [])->assertForbidden();
        $this->actingAs($staff)->postJson('/api/v1/orders', $this->payload())->assertForbidden();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_assessment_validation_messages(): void
    {
        $this->actingAs($this->manager)->postJson('/api/v1/orders', ['items' => []])
            ->assertUnprocessable()
            ->assertJsonPath('errors.customer_id.0', 'Customer is required.')
            ->assertJsonPath('errors.items.0', 'Order must contain at least one product.');

        $errors = $this->actingAs($this->manager)->postJson('/api/v1/orders', [
            'customer_id' => 99999,
            'items' => [['product_id' => 99999, 'quantity' => 0]],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.customer_id.0', 'Customer must exist and be active.')
            ->json('errors');

        // Item error keys contain dots (items.0.quantity), so read them from the array.
        $this->assertSame('Product must exist and be active.', $errors['items.0.product_id'][0]);
        $this->assertSame('Quantity must be greater than 0.', $errors['items.0.quantity'][0]);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_rejects_inactive_customer_inactive_product_and_duplicates(): void
    {
        $inactiveCustomer = Customer::factory()->inactive()->create();
        $discontinued = Product::factory()->discontinued()->create();

        $errors = $this->actingAs($this->manager)->postJson('/api/v1/orders', $this->payload([
            'customer_id' => $inactiveCustomer->id,
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 1],
                ['product_id' => $this->productA->id, 'quantity' => 2],
                ['product_id' => $discontinued->id, 'quantity' => 1],
            ],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'items.0.product_id', 'items.2.product_id'])
            ->json('errors');

        $this->assertSame('Each product can only be added once.', $errors['items.0.product_id'][0]);
    }

    public function test_rejects_bad_discount_and_assignee(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $inactiveStaff = User::factory()->inactive()->create()->assignRole('staff');

        $this->actingAs($this->manager)->postJson('/api/v1/orders', $this->payload(['discount_type' => 'percent', 'discount_value' => 10001, 'assigned_to' => $admin->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.discount_value.0', 'A percentage discount cannot exceed 100%.')
            ->assertJsonPath('errors.assigned_to.0', 'The assignee must be an active manager or staff member.');

        $this->actingAs($this->manager)->postJson('/api/v1/orders', $this->payload(['assigned_to' => $inactiveStaff->id, 'status' => 'completed']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to', 'status']);
    }

    public function test_a_failure_after_the_order_insert_rolls_everything_back(): void
    {
        // Fail right after the order row is written, before items and history.
        Order::created(fn () => throw new RuntimeException('Simulated failure'));

        $this->actingAs($this->manager)->postJson('/api/v1/orders', $this->payload())
            ->assertServerError()
            ->assertJsonPath('message', 'Something went wrong. Please try again later.');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_status_histories', 0);
        $this->assertDatabaseCount('order_activities', 0);
    }
}
