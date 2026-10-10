<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            // Items belong to the order: they go when the order is removed.
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            // A product that appears on an order cannot be hard-deleted.
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            // Snapshot at order time: later product edits don't change past orders.
            $table->string('product_name', 191);
            $table->string('sku', 64);
            $table->unsignedInteger('unit_price');
            $table->unsignedSmallInteger('tax_rate_bps');
            $table->unsignedInteger('quantity');
            // Line amounts in cents, computed by the backend.
            $table->unsignedBigInteger('line_subtotal');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount');
            $table->unsignedBigInteger('line_total');
            $table->timestamps();

            // Load the items of one order.
            $table->index('order_id', 'idx_order_items_order_id');
            // "Orders containing product X" search (whereExists on order_items).
            $table->index(['product_id', 'order_id'], 'idx_order_items_product_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
