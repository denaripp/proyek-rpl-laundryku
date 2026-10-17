<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('rating')->check('rating between 1 and 5');
            $table->text('comment')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('moderation_reason')->nullable();
            $table->timestamps();
            $table->unique('order_id');
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};