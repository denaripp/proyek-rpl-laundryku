<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vehicle_type', 50)->nullable();
            $table->string('vehicle_plate', 20)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_available')->default(true);
            $table->decimal('current_latitude', 10, 8)->nullable();
            $table->decimal('current_longitude', 11, 8)->nullable();
            $table->timestamp('last_location_update')->nullable();
            $table->json('service_area')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('total_tasks')->default(0);
            $table->unsignedInteger('completed_tasks')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique('user_id');
            $table->index('is_available');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couriers');
    }
};