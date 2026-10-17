<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SECURITY (Tahap 22 / follow-up) — add `phone` column to users.
 *
 * Reason: AuthController::register() + updateCompleteProfile() wrote
 * `phone` to users table but the column never existed (only in
 * Customer/Store/Address tables). Caused SQLSTATE[42S22] on submit.
 * Adding as nullable to preserve existing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};