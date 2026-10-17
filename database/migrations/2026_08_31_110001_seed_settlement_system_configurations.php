<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_configurations')->insertOrIgnore([
            [
                'key' => 'SETTLEMENT_PERIOD',
                'value' => json_encode([
                    'placeholder' => '[SETTLEMENT_PERIOD]',
                    'interval' => 'weekly', // weekly | biweekly | monthly
                    'description' => 'Periode settlement platform ke store. Placeholder config, bukan hardcode. Diubah via admin setting.',
                ]),
                'description' => 'Settlement period configuration.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'MARKETPLACE_COMMISSION',
                'value' => json_encode([
                    'placeholder' => '[MARKETPLACE_COMMISSION]',
                    'rate_percent' => 10,
                    'description' => 'Default marketplace commission rate (%) jika store tidak punya commission override. Bisa di-override per-store via commission_settings.',
                ]),
                'description' => 'Default marketplace commission rate for stores.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'PLATFORM_FEE',
                'value' => json_encode([
                    'placeholder' => '[PLATFORM_FEE]',
                    'fee_type' => 'fixed', // fixed | percentage
                    'amount' => 2000,
                    'rate_percent' => 0,
                    'description' => 'Platform fee per order yang dipotong dari settlement store.',
                ]),
                'description' => 'Platform fee deducted from store settlements.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('system_configurations')
            ->whereIn('key', ['SETTLEMENT_PERIOD', 'MARKETPLACE_COMMISSION', 'PLATFORM_FEE'])
            ->delete();
    }
};