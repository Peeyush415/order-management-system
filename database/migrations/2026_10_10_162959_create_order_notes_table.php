<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            // Notes panel of one order, newest first.
            $table->index(['order_id', 'created_at'], 'idx_order_notes_order_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_notes');
    }
};
