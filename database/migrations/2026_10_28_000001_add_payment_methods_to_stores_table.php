<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'qris_enabled')) {
                $table->boolean('qris_enabled')->default(false)->after('cod_enabled');
            }
            if (!Schema::hasColumn('stores', 'va_enabled')) {
                $table->boolean('va_enabled')->default(false)->after('qris_enabled');
            }
            if (!Schema::hasColumn('stores', 'va_banks')) {
                $table->json('va_banks')->nullable()->after('va_enabled');
            }
        });

        // Default toko lama: COD aktif, lainnya mati
        DB::table('stores')->update([
            'cod_enabled' => true,
            'qris_enabled' => false,
            'va_enabled' => false,
        ]);
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'va_banks')) {
                $table->dropColumn('va_banks');
            }
            if (Schema::hasColumn('stores', 'va_enabled')) {
                $table->dropColumn('va_enabled');
            }
            if (Schema::hasColumn('stores', 'qris_enabled')) {
                $table->dropColumn('qris_enabled');
            }
        });
    }
};
