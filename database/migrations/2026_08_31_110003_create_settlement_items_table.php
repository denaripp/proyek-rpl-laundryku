<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->decimal('order_total', 12, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->decimal('platform_fee_amount', 12, 2);
            $table->decimal('net_amount', 12, 2);
            $table->timestamps();
            $table->unique('order_id'); // 1 order hanya boleh masuk 1 settlement
            $table->index('settlement_period_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_items');
    }
};