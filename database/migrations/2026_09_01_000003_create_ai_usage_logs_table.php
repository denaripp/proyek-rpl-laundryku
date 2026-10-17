<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 10, 6)->default(0);
            $table->unsignedInteger('tool_rounds')->default(0);
            $table->enum('status', ['success', 'provider_error', 'rate_limited', 'abuse_blocked'])->default('success');
            $table->json('flags')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};