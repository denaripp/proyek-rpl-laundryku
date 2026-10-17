<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('channel'); // email / whatsapp
            $table->string('destination_hash');
            $table->string('otp_hash');
            $table->timestamp('expires_at');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
            $table->index(['destination_hash', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
    }
};
