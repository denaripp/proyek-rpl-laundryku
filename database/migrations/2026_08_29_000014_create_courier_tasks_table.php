<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('courier_id')->constrained()->onDelete('cascade');
            $table->enum('task_type', ['pickup', 'delivery']);
            $table->enum('status', ['assigned', 'accepted', 'arrived', 'picked_up', 'delivered', 'failed', 'cancelled'])->default('assigned');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->timestamps();
            // unique constraint
            $table->unique(['order_id', 'task_type']);
            $table->index('status');
            $table->index(['courier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_tasks');
    }
};