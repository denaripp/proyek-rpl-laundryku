<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add is_sponsored column if not exists
        if (!Schema::hasColumn('stores', 'is_sponsored')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->boolean('is_sponsored')->default(false)->after('suspended_at');
            });
        }

        // Add index on (status, is_active) if not exists
        if (!Schema::hasIndex('stores', 'stores_status_is_active_index')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->index(['status', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        // Remove index if exists
        if (Schema::hasIndex('stores', 'stores_status_is_active_index')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropIndex('stores_status_is_active_index');
            });
        }

        // Remove is_sponsored column if exists
        if (Schema::hasColumn('stores', 'is_sponsored')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropColumn('is_sponsored');
            });
        }
    }
};