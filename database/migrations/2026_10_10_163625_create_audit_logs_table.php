<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Compliance record of raw changes across all entities. Admin-only, append-only.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Null for system changes (seeders, scheduled jobs).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 30);
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id');
            // Hidden fields (password, tokens) are never written here.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // History of one record (e.g. order #5), newest first.
            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'idx_audit_logs_auditable_created');
            // "What did this user change?"; also serves the user_id foreign key.
            $table->index(['user_id', 'created_at'], 'idx_audit_logs_user_created');
            // Audit screen filtered by event type.
            $table->index(['event', 'created_at'], 'idx_audit_logs_event_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
