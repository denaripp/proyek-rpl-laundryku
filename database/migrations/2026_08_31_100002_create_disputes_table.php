<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->string('complaint_number')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 100);
            $table->text('description');
            $table->enum('status', [
                'complaint_submitted',
                'under_review',
                'investigation',
                'resolved',
                'rejected',
            ])->default('complaint_submitted');
            $table->json('evidence')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_outcome', 50)->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->nullOnDelete();
            $table->timestamp('sla_due_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};