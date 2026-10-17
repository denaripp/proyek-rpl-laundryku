<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_messages')) {
            Schema::create('ai_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('ai_conversations')->onDelete('cascade');
                $table->text('content');
                $table->string('role');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};