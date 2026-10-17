<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_login_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('attempted_at')->useCurrent();
            $table->index('user_id');
            $table->index('email');
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_login_attempts');
    }
};