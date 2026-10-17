<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value');
            $table->text('description')->nullable();
            $table->boolean('is_editable')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_configurations');
    }
};