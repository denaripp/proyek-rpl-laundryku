<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('channel', 30);
            $table->string('event_type', 50);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'channel', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};