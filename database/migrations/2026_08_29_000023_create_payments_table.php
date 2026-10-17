<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->enum('payment_method', ['online', 'cod']);
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'refund_requested', 'refund_processing', 'refunded', 'cancelled'])->default('pending');
            $table->string('xendit_invoice_id')->nullable();
            $table->string('xendit_payment_id')->nullable();
            $table->string('xendit_payment_method', 50)->nullable();
            $table->string('xendit_payment_channel', 50)->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('customer_ip', 45)->nullable();
            $table->text('customer_user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique('order_id');
            $table->index('status');
            $table->index('xendit_invoice_id');
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};