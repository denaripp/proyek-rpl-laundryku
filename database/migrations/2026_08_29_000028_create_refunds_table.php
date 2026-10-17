<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['requested', 'processing', 'completed', 'failed', 'cancelled'])->default('requested');
            $table->text('reason');
            $table->string('method', 50);
            $table->string('refund_reference')->nullable();
            $table->string('xendit_refund_id')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique('payment_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};