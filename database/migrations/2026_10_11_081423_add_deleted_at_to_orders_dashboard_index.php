<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EXPLAIN showed the dashboard index was not covering: every query also filters
 * `deleted_at IS NULL` (soft deletes), so MySQL went back to the table for each row
 * ("Using where"). With deleted_at in the index the dashboard aggregates are answered
 * from the index alone ("Using index").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_date_status_total');
            $table->index(['order_date', 'status', 'grand_total', 'deleted_at'], 'idx_orders_date_status_total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_date_status_total');
            $table->index(['order_date', 'status', 'grand_total'], 'idx_orders_date_status_total');
        });
    }
};
