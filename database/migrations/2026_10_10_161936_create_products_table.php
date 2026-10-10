<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64);
            $table->string('name', 191);
            $table->text('description')->nullable();
            // Money in integer cents, tax in basis points (1000 = 10%): no float rounding.
            $table->unsignedInteger('price');
            $table->unsignedSmallInteger('tax_rate_bps')->default(1000);
            // Unsigned: the database itself refuses negative stock.
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(10);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            // One product per SKU.
            $table->unique('sku', 'idx_products_sku');
            // Product pickers and lists: active products sorted by name.
            $table->index(['status', 'name'], 'idx_products_status_name');
            // Search box by name or SKU.
            $table->fullText(['name', 'sku'], 'idx_products_search');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
