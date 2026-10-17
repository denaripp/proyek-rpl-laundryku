<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('ai_chat_sessions')->onDelete('cascade');
            $table->enum('role', ['user', 'assistant', 'system', 'tool']);
            $table->text('content');
            $table->json('function_call')->nullable();
            $table->json('function_result')->nullable();
            $table->json('token_usage')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            // No updated_at column in schema, but we add if needed; schema says only created_at
            // We'll keep created_at only as per schema
        });
        // Add index separately if needed, but already defined in schema
        // Indexes already defined in the table definition, remove duplicate
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};