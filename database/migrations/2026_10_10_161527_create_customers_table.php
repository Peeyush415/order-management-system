<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 191);
            $table->string('phone', 30)->nullable();
            $table->string('company', 150)->nullable();
            $table->string('address_line1', 191);
            $table->string('address_line2', 191)->nullable();
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100);
            // VARCHAR, not ENUM: adding a status later needs no ALTER on a big table.
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // One customer per email address.
            $table->unique('email', 'idx_customers_email');
            // Customer list: filter by status, newest first.
            $table->index(['status', 'created_at'], 'idx_customers_status_created_at');
            // Search box: LIKE '%x%' cannot use an index, FULLTEXT can.
            $table->fullText(['name', 'email', 'company'], 'idx_customers_search');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
