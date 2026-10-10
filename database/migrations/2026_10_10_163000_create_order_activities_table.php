<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Human-readable feed shown on the order page. Separate from audit_logs,
        // which keeps raw old/new diffs for admins only. Append-only.
        Schema::create('order_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            // Null for system actions (e.g. a scheduled job).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->string('description', 255);
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Paginated activity feed of one order, newest first.
            $table->index(['order_id', 'created_at'], 'idx_order_activities_order_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_activities');
    }
};
