<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispute_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50)->nullable();
            $table->text('note')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('dispute_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_events');
    }
};