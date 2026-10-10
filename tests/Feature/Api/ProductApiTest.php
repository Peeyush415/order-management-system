<?php

namespace Tests\Feature\Api;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    private function validPayload(array $overrides = []): array
    {
        return [
            'sku' => 'kb-100',
            'name' => 'Wireless Keyboard',
            'price' => 12550,
            'tax_rate_bps' => 1800,
            'low_stock_threshold' => 5,
            ...$overrides,
        ];
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();
    }

    public function test_staff_lists_products_with_formatted_price(): void
    {
        Product::factory()->create(['price' => 12550, 'stock_quantity' => 3, 'low_stock_threshold' => 10]);
        Product::factory()->discontinued()->create();

        $this->actingAs($this->userWithRole('staff'))
            ->getJson('/api/v1/products?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.price', 12550)
            ->assertJsonPath('data.0.price_formatted', '125.50')
            ->assertJsonPath('data.0.is_low_stock', true)
            ->assertJsonPath('data.0.status.value', 'active');
    }

    public function test_list_sorts_by_whitelisted_columns_only(): void
    {
        Product::factory()->create(['price' => 900]);
        Product::factory()->create(['price' => 100]);
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)
            ->getJson('/api/v1/products?sort=price&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.price', 100);

        $this->actingAs($staff)
            ->getJson('/api/v1/products?sort=tax_rate_bps')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    }

    public static function nonAdminRoles(): array
    {
        return ['staff' => ['staff'], 'manager' => ['manager']];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_only_admin_may_change_products(string $role): void
    {
        $product = Product::factory()->create();
        $user = $this->userWithRole($role);

        // 403 even with invalid input: permission is checked before validation.
        $this->actingAs($user)->postJson('/api/v1/products', [])->assertForbidden();
        $this->actingAs($user)->putJson("/api/v1/products/{$product->id}", [])->assertForbidden();
        $this->actingAs($user)->patchJson("/api/v1/products/{$product->id}/status", [])->assertForbidden();

        $this->assertDatabaseCount('products', 1);
    }

    public function test_admin_creates_a_product_with_opening_stock_in_the_ledger(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson('/api/v1/products', $this->validPayload(['initial_stock' => 40, 'stock_quantity' => 9999]))
            ->assertCreated()
            ->assertJsonPath('data.sku', 'KB-100')
            ->assertJsonPath('data.stock_quantity', 40)
            ->assertJsonPath('data.tax_rate_bps', 1800);

        $product = Product::firstWhere('sku', 'KB-100');
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'user_id' => $admin->id,
            'type' => StockMovementType::ManualAdjustment->value,
            'quantity' => 40,
            'balance_after' => 40,
            'reason' => 'Opening stock',
        ]);
    }

    public function test_product_without_initial_stock_starts_at_zero_with_no_ledger_rows(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->postJson('/api/v1/products', $this->validPayload(['tax_rate_bps' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tax_rate_bps']);

        $this->actingAs($this->userWithRole('admin'))
            ->postJson('/api/v1/products', collect($this->validPayload())->except('tax_rate_bps')->all())
            ->assertCreated()
            ->assertJsonPath('data.stock_quantity', 0)
            ->assertJsonPath('data.tax_rate_bps', 1000);

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_create_validates_sku_and_price(): void
    {
        Product::factory()->create(['sku' => 'KB-100']);

        $this->actingAs($this->userWithRole('admin'))
            ->postJson('/api/v1/products', $this->validPayload(['price' => 125.5]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.sku.0', 'A product with this SKU already exists.')
            ->assertJsonPath('errors.price.0', 'The price must be a whole number of cents.');

        $this->actingAs($this->userWithRole('admin'))
            ->postJson('/api/v1/products', $this->validPayload(['sku' => 'BAD SKU!', 'price' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku', 'price']);
    }

    public function test_admin_updates_a_product_but_never_its_stock(): void
    {
        $product = Product::factory()->create(['sku' => 'KB-100', 'stock_quantity' => 25]);

        $this->actingAs($this->userWithRole('admin'))
            ->putJson("/api/v1/products/{$product->id}", $this->validPayload(['name' => 'Silent Keyboard', 'initial_stock' => 500, 'stock_quantity' => 500]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Silent Keyboard')
            ->assertJsonPath('data.stock_quantity', 25);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Silent Keyboard', 'stock_quantity' => 25]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_admin_discontinues_a_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->patchJson("/api/v1/products/{$product->id}/status", ['status' => 'discontinued'])
            ->assertOk()
            ->assertJsonPath('data.status.label', 'Discontinued');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'discontinued']);
    }

    public function test_show_returns_404_for_a_missing_product(): void
    {
        $this->actingAs($this->userWithRole('staff'))
            ->getJson('/api/v1/products/999')
            ->assertNotFound();
    }
}
