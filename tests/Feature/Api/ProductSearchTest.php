<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/**
 * FULLTEXT only sees committed rows, so this test commits for real (see CustomerSearchTest)
 * and truncates afterwards.
 */
class ProductSearchTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    public function test_search_by_name_prefix_or_sku(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->actingAs(User::factory()->create()->assignRole('staff'));

        Product::factory()->create(['name' => 'Wireless Keyboard', 'sku' => 'KB-100']);
        Product::factory()->create(['name' => 'Wired Mouse', 'sku' => 'MS-200']);

        $this->getJson('/api/v1/products?search=keyb')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'KB-100');

        // Two-letter input falls back to a prefix match on SKU or name.
        $this->getJson('/api/v1/products?search=MS')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Wired Mouse');
    }
}
