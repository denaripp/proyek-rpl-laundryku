<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type', 50);
            $table->string('title');
            $table->text('content');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->json('delivered_via')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->text('email_error')->nullable();
            $table->timestamps();
            $table->index('read_at');
            $table->index('created_at');
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};