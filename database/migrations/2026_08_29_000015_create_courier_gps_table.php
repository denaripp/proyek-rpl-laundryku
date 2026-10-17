<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_gps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('courier_tasks')->onDelete('cascade');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('accuracy_meters', 6, 2)->nullable();
            $table->decimal('heading', 5, 2)->nullable();
            $table->decimal('speed_kmh', 6, 2)->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->index('recorded_at');
            $table->index(['task_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_gps');
    }
};