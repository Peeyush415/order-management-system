<?php

namespace Tests\Feature\Api;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create()->assignRole('admin');
        $this->product = Product::factory()->create(['stock_quantity' => 10]);
    }

    private function adjust(User $user, array $payload)
    {
        return $this->actingAs($user)->postJson("/api/v1/products/{$this->product->id}/stock-adjustments", $payload);
    }

    public function test_admin_adds_and_removes_stock_with_a_ledger_entry(): void
    {
        $this->adjust($this->admin, ['quantity' => 25, 'reason' => 'Goods received'])
            ->assertOk()
            ->assertJsonPath('message', 'Stock adjusted successfully')
            ->assertJsonPath('data.stock_quantity', 35);

        $this->adjust($this->admin, ['quantity' => -5, 'reason' => 'Damaged in storage'])
            ->assertOk()
            ->assertJsonPath('data.stock_quantity', 30);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id, 'order_id' => null, 'user_id' => $this->admin->id,
            'type' => StockMovementType::ManualAdjustment->value, 'quantity' => -5, 'balance_after' => 30,
            'reason' => 'Damaged in storage',
        ]);
    }

    public function test_stock_can_never_go_below_zero(): void
    {
        $this->adjust($this->admin, ['quantity' => -11, 'reason' => 'Count correction'])
            ->assertUnprocessable()
            ->assertJsonPath('message', "Insufficient stock for {$this->product->name}: 10 available, 11 requested.");

        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_validation(): void
    {
        $this->adjust($this->admin, ['quantity' => 0])
            ->assertUnprocessable()
            ->assertJsonPath('errors.quantity.0', 'The adjustment cannot be zero.')
            ->assertJsonPath('errors.reason.0', 'Please give a reason for the stock adjustment.');
    }

    public function test_only_product_managers_may_adjust(): void
    {
        foreach (['manager', 'staff'] as $role) {
            $this->adjust(User::factory()->create()->assignRole($role), ['quantity' => 5, 'reason' => 'x'])->assertForbidden();
        }

        $this->assertSame(10, $this->product->fresh()->stock_quantity);
    }
}
