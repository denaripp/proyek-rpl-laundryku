<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Chatbot Sessions Table (PRD 11.7)
        Schema::create('chatbot_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('context_type', 50)->nullable()->default('general'); // general, faq, order
            $table->uuid('context_id')->nullable();
            $table->string('provider', 50)->default('openai');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['context_type', 'context_id']);
        });

        // 2. Chatbot Messages Table (PRD 11.7)
        Schema::create('chatbot_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('chatbot_sessions')->onDelete('cascade');
            $table->string('role', 20); // user, assistant, system
            $table->text('content');
            $table->integer('token_count')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbot_messages');
        Schema::dropIfExists('chatbot_sessions');
    }
};
