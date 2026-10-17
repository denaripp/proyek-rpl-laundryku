<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xendit_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->json('payload');
            $table->string('signature')->nullable();
            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xendit_webhooks');
    }
};