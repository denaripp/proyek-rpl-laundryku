<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_periods', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->string('interval', 20); // weekly | biweekly | monthly
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('platform_fee_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->unsignedInteger('order_count')->default(0);
            $table->enum('status', ['generated', 'verified', 'paid'])->default('generated');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->json('config_snapshot')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'status']);
            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_periods');
    }
};