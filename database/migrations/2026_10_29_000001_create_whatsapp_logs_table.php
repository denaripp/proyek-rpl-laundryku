<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_logs', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_phone', 32);
            $table->string('recipient_role', 50)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 100);
            $table->text('message');
            $table->string('driver', 30)->default('log');
            $table->string('status', 30)->default('pending'); // sent, failed, skipped
            $table->text('error')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->index(['recipient_phone', 'status']);
            $table->index('event');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_logs');
    }
};
