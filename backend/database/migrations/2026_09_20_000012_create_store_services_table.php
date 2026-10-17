<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('store_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('stores')->onDelete('cascade');
            $table->foreignUuid('category_id')->constrained('service_categories')->onDelete('cascade');
            $table->string('name');
            $table->string('pricing_unit', 20)->default('kg'); // kg, item
            $table->bigInteger('price_per_unit'); // Rupiah amount
            $table->bigInteger('minimum_charge')->default(0); // Minimum charge in Rupiah
            $table->integer('estimated_duration_hours')->default(24);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['store_id', 'is_active']);
            $table->index(['category_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_services');
    }
};
