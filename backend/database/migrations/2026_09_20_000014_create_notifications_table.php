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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('channel', 30)->default('in_app'); // in_app, whatsapp, push
            $table->string('event_type', 50); // PRICE_APPROVAL_REQUIRED, ORDER_STATUS_CHANGED, etc.
            $table->string('title');
            $table->text('message');
            $table->jsonb('payload')->nullable();
            $table->string('status', 20)->default('unread')->index(); // unread, read, sent, failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
