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
        // 1. Conversations Table (PRD 11.7)
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignUuid('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('store_id')->constrained('stores')->onDelete('cascade');
            $table->string('status', 30)->default('active'); // active, closed, archived
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();

            $table->index(['customer_id', 'store_id']);
            $table->index(['store_id', 'status']);
            $table->index(['order_id']);
        });

        // 2. Chat Messages Table (PRD 11.7)
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->onDelete('cascade');
            $table->foreignUuid('sender_id')->constrained('users')->onDelete('cascade');
            $table->string('message_type', 20)->default('text'); // text, image
            $table->text('body')->nullable();
            $table->string('attachment_key')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('conversations');
    }
};
