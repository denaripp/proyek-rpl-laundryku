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
        Schema::create('courier_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->text('identity_encrypted')->nullable();
            $table->string('ktp_storage_key')->nullable();
            $table->string('photo_storage_key')->nullable();
            $table->string('vehicle_type'); // motor, mobil, etc.
            $table->text('vehicle_plate_encrypted')->nullable();
            $table->string('verification_status')->default('pending')->index(); // pending, approved, rejected
            $table->string('status')->default('inactive')->index(); // active, inactive
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courier_profiles');
    }
};
