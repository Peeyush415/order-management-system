<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Set right after insert as ORD-(10000 + id), so two requests can never get the same number.
            // Nullable only for that moment; a unique index allows NULLs.
            $table->string('order_number', 20)->nullable();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            // VARCHAR, not ENUM: adding a status needs no ALTER on a 1M-row table.
            $table->string('status', 20);
            // 1 low … 4 urgent, so sorting by priority sorts by severity.
            $table->unsignedTinyInteger('priority')->default(2);
            $table->date('order_date');
            // Money in integer cents; discount_value is cents (fixed) or basis points (percent).
            $table->string('discount_type', 10)->nullable();
            $table->unsignedInteger('discount_value')->default(0);
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('grand_total')->default(0);
            // Set when stock is deducted; cancel restocks only if this is set.
            $table->timestamp('stock_deducted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            // Optimistic locking: an update with a stale version gets 409 Conflict.
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            // Search by order number.
            $table->unique('order_number', 'idx_orders_order_number');
            // Status filter with date sort; status counts.
            $table->index(['status', 'order_date'], 'idx_orders_status_order_date');
            // Staff "my orders", the most frequent staff query.
            $table->index(['assigned_to', 'status', 'order_date'], 'idx_orders_assigned_status_date');
            // Customer detail page; also serves the customer_id foreign key.
            $table->index(['customer_id', 'order_date'], 'idx_orders_customer_order_date');
            // Covering index for the dashboard revenue trend: no table lookup needed.
            $table->index(['order_date', 'status', 'grand_total'], 'idx_orders_date_status_total');
            // No index on priority alone: 4 values, the optimizer would not use it.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
