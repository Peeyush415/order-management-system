<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_an_active_product_with_integer_money(): void
    {
        $product = Product::factory()->create(['price' => 12550])->fresh();

        $this->assertSame(ProductStatus::Active, $product->status);
        $this->assertSame(12550, $product->price);
        $this->assertSame('Discontinued', ProductStatus::Discontinued->label());
    }

    public function test_duplicate_sku_is_rejected_by_the_database(): void
    {
        Product::factory()->create(['sku' => 'SKU-1']);

        $this->expectException(QueryException::class);

        Product::factory()->create(['sku' => 'SKU-1']);
    }

    public function test_low_stock_is_detected_at_or_below_the_threshold(): void
    {
        $this->assertTrue(Product::factory()->make(['stock_quantity' => 10])->isLowStock());
        $this->assertFalse(Product::factory()->make(['stock_quantity' => 11])->isLowStock());
        $this->assertTrue(Product::factory()->lowStock()->make()->isLowStock());
    }

    public function test_database_refuses_negative_stock(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);

        $this->expectException(QueryException::class);

        // Raw decrement, as a buggy code path would do it.
        Product::whereKey($product->id)->decrement('stock_quantity', 6);
    }
}
