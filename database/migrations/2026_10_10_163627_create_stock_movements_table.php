<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ledger: every stock change is one row, so the current stock can always be explained.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            // Null for manual adjustments. Restrict: ledger rows must not lose their order.
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            // Signed: negative for a deduction, positive for a restock.
            $table->integer('quantity');
            $table->unsignedInteger('balance_after');
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Stock history of one product; also serves the product_id foreign key.
            $table->index(['product_id', 'created_at'], 'idx_stock_movements_product_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
