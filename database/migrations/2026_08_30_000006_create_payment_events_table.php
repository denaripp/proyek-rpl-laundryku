<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->string('event_type', 50);
            $table->string('xendit_event_id', 64)->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->index(['payment_id', 'event_type']);
            $table->unique(['payment_id', 'xendit_event_id'], 'payment_events_unique_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};