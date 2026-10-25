<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('user_roles') && !Schema::hasColumn('user_roles', 'status')) {
            Schema::table('user_roles', function (Blueprint $table) {
                $table->string('status', 50)->default('active')->after('store_id');
            });
        }

        // Pastikan semua baris user_roles lama berstatus 'active' setelah migrasi
        if (Schema::hasTable('user_roles') && Schema::hasColumn('user_roles', 'status')) {
            DB::table('user_roles')
                ->whereNull('status')
                ->orWhere('status', '')
                ->update(['status' => 'active']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('user_roles') && Schema::hasColumn('user_roles', 'status')) {
            Schema::table('user_roles', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
