<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'pickup_address')) {
                $table->text('pickup_address')->nullable()->after('store_id');
            }
            if (!Schema::hasColumn('orders', 'pickup_latitude')) {
                $table->decimal('pickup_latitude', 10, 8)->nullable()->after('pickup_address');
            }
            if (!Schema::hasColumn('orders', 'pickup_longitude')) {
                $table->decimal('pickup_longitude', 11, 8)->nullable()->after('pickup_latitude');
            }
            if (!Schema::hasColumn('orders', 'pickup_address_detail')) {
                $table->json('pickup_address_detail')->nullable()->after('pickup_longitude');
            }
            if (!Schema::hasColumn('orders', 'delivery_address')) {
                $table->text('delivery_address')->nullable()->after('pickup_address_detail');
            }
            if (!Schema::hasColumn('orders', 'delivery_latitude')) {
                $table->decimal('delivery_latitude', 10, 8)->nullable()->after('delivery_address');
            }
            if (!Schema::hasColumn('orders', 'delivery_longitude')) {
                $table->decimal('delivery_longitude', 11, 8)->nullable()->after('delivery_latitude');
            }
            if (!Schema::hasColumn('orders', 'delivery_address_detail')) {
                $table->json('delivery_address_detail')->nullable()->after('delivery_longitude');
            }
            if (!Schema::hasColumn('orders', 'pickup_address_id')) {
                $table->unsignedBigInteger('pickup_address_id')->nullable()->after('delivery_address_detail');
            }
            if (!Schema::hasColumn('orders', 'delivery_address_id')) {
                $table->unsignedBigInteger('delivery_address_id')->nullable()->after('pickup_address_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = [
                'pickup_address',
                'pickup_latitude',
                'pickup_longitude',
                'pickup_address_detail',
                'delivery_address',
                'delivery_latitude',
                'delivery_longitude',
                'delivery_address_detail',
                'pickup_address_id',
                'delivery_address_id',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
