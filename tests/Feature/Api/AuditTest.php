<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create()->assignRole('admin');
    }

    private function lastAudit(object $model): AuditLog
    {
        return AuditLog::query()
            ->where('auditable_type', $model->getMorphClass())
            ->where('auditable_id', $model->getKey())
            ->latest('id')
            ->firstOrFail();
    }

    public function test_customer_update_stores_only_the_changed_fields(): void
    {
        $customer = Customer::factory()->create(['city' => 'Pune', 'phone' => '111']);
        $payload = collect($customer->only(['name', 'email', 'phone', 'company', 'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country']))
            ->merge(['city' => 'Mumbai'])
            ->all();

        $this->actingAs($this->admin)
            ->withHeader('User-Agent', 'AuditTest/1.0')
            ->putJson("/api/v1/customers/{$customer->id}", $payload)
            ->assertOk();

        $audit = $this->lastAudit($customer);
        $this->assertSame('updated', $audit->event);
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame(['city' => 'Pune'], $audit->old_values);
        $this->assertSame(['city' => 'Mumbai'], $audit->new_values);
        $this->assertSame('127.0.0.1', $audit->ip_address);
        $this->assertSame('AuditTest/1.0', $audit->user_agent);
    }

    public function test_product_update_is_audited(): void
    {
        $product = Product::factory()->create(['price' => 1000]);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/products/{$product->id}", ['sku' => $product->sku, 'name' => $product->name, 'price' => 1500])
            ->assertOk();

        $audit = $this->lastAudit($product);
        $this->assertSame(['price' => 1000], $audit->old_values);
        $this->assertSame(['price' => 1500], $audit->new_values);
    }

    public function test_order_lifecycle_is_audited_with_named_events(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 50]);
        $staff = User::factory()->create()->assignRole('staff');

        $orderId = $this->actingAs($this->admin)->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');
        $order = Order::findOrFail($orderId);

        $this->actingAs($this->admin)->postJson("/api/v1/orders/{$orderId}/assign", ['assigned_to' => $staff->id])->assertOk();
        $this->actingAs($this->admin)->postJson("/api/v1/orders/{$orderId}/status", ['status' => 'cancelled', 'remarks' => 'Test'])->assertOk();
        $this->actingAs($this->admin)->deleteJson("/api/v1/orders/{$orderId}")->assertNoContent();

        $events = AuditLog::where('auditable_type', $order->getMorphClass())->where('auditable_id', $orderId)->orderBy('id')->pluck('event')->all();
        // The order number is set quietly at creation, so no extra "updated" row.
        $this->assertSame(['created', 'assignment_changed', 'status_changed', 'deleted'], $events);

        $assignment = AuditLog::where('auditable_id', $orderId)->where('event', 'assignment_changed')->first();
        $this->assertSame(['assigned_to' => null], $assignment->old_values);
        $this->assertSame(['assigned_to' => $staff->id], $assignment->new_values);

        $status = AuditLog::where('auditable_id', $orderId)->where('event', 'status_changed')->first();
        $this->assertSame('pending', $status->old_values['status']);
        $this->assertSame('cancelled', $status->new_values['status']);
        $this->assertArrayNotHasKey('version', $status->new_values);
    }

    public function test_order_update_is_audited(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Confirmed])->fresh();
        OrderItem::factory()->for($order)->create();

        $this->actingAs($this->admin)
            ->putJson("/api/v1/orders/{$order->id}", ['priority' => 4, 'version' => $order->version])
            ->assertOk();

        $audit = $this->lastAudit($order);
        $this->assertSame('updated', $audit->event);
        $this->assertSame(['priority' => 2], $audit->old_values);
        $this->assertSame(['priority' => 4], $audit->new_values);
    }

    public function test_no_audit_survives_a_rolled_back_change(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        Order::created(fn () => throw new RuntimeException('Simulated failure'));

        $this->actingAs($this->admin)->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertServerError();

        $this->assertSame(0, AuditLog::where('auditable_type', (new Order)->getMorphClass())->count());
    }
}
