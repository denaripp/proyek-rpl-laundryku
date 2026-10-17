<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->decimal('price_per_unit', 10, 2);
            $table->enum('unit', ['kg', 'item'])->default('kg');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['store_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_prices');
    }
};