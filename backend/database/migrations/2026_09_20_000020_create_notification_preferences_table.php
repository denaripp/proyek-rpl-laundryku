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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('channel', 30); // in_app, whatsapp, email
            $table->string('event_type', 50)->default('all'); // all, ORDER_CREATED, ORDER_STATUS_CHANGED, PRICE_ESTIMATED, PAYMENT_SUCCESS, ORDER_COMPLETED, COMPLAINT_UPDATED
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'channel', 'event_type']);
            $table->index(['user_id', 'is_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
