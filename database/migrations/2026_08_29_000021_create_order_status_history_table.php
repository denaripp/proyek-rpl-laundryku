<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('status', 50);
            $table->string('previous_status', 50)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_role', 50)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
};