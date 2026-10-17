<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('display_name', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('category', 50)->nullable();
            $table->timestamps();
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};