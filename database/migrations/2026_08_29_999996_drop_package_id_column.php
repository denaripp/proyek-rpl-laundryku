<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'package_id')) {
            // Hapus foreign key jika ada (nama default orders_package_id_foreign)
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeignIfExists('orders_package_id_foreign');
            });

            // Hapus kolom
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('package_id');
            });
        }
    }

    public function down(): void
    {
        // Rollback tidak bisa mengembalikan kolom yang sudah dihapus
    }
};