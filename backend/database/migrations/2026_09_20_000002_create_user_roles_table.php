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
        Schema::create('user_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedSmallInteger('role_id');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->uuid('store_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['user_id', 'role_id']);
            $table->unique(['user_id', 'role_id', 'store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
