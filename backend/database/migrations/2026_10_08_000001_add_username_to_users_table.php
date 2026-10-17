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
        // 1. Add username column to users table (nullable, unique, after name)
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->unique()->after('name');
            }
        });

        // 2. Add performance index for orders filtering and aggregation
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'orders_status_created_at_idx');
            $table->index(['status', 'completed_at'], 'orders_status_completed_at_idx');
        });

        // 3. Add performance index for payments filtering
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'payments_status_created_at_idx');
        });

        // 4. Add performance index for audit logs
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('created_at', 'audit_logs_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_created_at_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_status_created_at_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_created_at_idx');
            $table->dropIndex('orders_status_completed_at_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'username')) {
                $table->dropColumn('username');
            }
        });
    }
};
