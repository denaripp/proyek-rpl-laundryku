<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('commission_rate', 5, 2);
            $table->enum('fee_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('fixed_amount', 10, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_settings');
    }
};