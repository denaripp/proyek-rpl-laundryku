<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_user_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('address_text');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('service_radius_km', 4, 2)->default(5.00);
            $table->jsonb('opening_hours')->nullable();
            $table->string('approval_status')->default('pending')->index(); // pending, approved, rejected
            $table->string('operational_status')->default('inactive')->index(); // active, inactive, suspended
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['approval_status', 'operational_status']);
        });

        // Add CHECK constraint for service_radius_km <= 10 (PostgreSQL)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stores ADD CONSTRAINT check_service_radius_km CHECK (service_radius_km <= 10.00)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
