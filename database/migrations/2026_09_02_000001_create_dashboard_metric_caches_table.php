<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dashboard_metric_caches', function (Blueprint $table) {
            $table->id();
            // scope: 'platform', 'store:<id>', 'courier:<id>'
            $table->string('scope', 80)->index();
            $table->string('metric_key', 80);
            $table->json('metric_value');
            $table->timestamp('computed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['scope', 'metric_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_metric_caches');
    }
};
