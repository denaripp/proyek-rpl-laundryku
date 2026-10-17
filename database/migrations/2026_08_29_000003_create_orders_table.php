<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->onDelete('cascade');
                $table->foreignId('package_id')->constrained('laundry_packages')->onDelete('cascade');
                $table->decimal('weight', 8, 2);
                $table->decimal('total_price', 10, 2);
                $table->string('status');
                $table->timestamp('order_date');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};