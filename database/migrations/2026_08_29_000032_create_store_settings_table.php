<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('key', 100);
            $table->json('value');
            $table->timestamps();
            $table->unique(['store_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};