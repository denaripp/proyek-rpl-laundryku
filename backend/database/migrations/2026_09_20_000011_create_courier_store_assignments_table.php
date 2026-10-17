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
        Schema::create('courier_store_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('courier_id')->constrained('courier_profiles')->onDelete('cascade');
            $table->foreignUuid('store_id')->constrained('stores')->onDelete('cascade');
            $table->foreignUuid('recruited_by')->constrained('users');
            $table->string('status')->default('active')->index(); // active, inactive
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('store_id');
        });

        // Add partial unique index: only 1 active assignment per courier (PostgreSQL / SQLite)
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'])) {
            DB::statement("CREATE UNIQUE INDEX idx_courier_single_active ON courier_store_assignments (courier_id) WHERE status = 'active'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courier_store_assignments');
    }
};
