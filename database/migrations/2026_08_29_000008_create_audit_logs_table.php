<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_role', 50)->nullable();
            $table->string('action', 100);
            $table->string('resource_type', 50);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('before_value')->nullable();
            $table->json('after_value')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            // indexes
            $table->index('actor_id');
            $table->index(['resource_type', 'resource_id']);
            $table->index('action');
            $table->index('created_at');
        });
        // additional index
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['actor_id', 'created_at'], 'idx_audit_logs_actor_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};