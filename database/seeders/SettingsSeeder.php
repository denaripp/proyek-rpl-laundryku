<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Seed placeholder settings with sensible defaults.
     */
    public function run(): void
    {
        $settings = [
            // General & Business
            [
                'key' => 'COD_MAX_AMOUNT',
                'value' => '500000',
                'type' => 'integer',
                'group' => 'payment',
                'description' => 'Maksimum nominal pesanan yang diizinkan menggunakan metode bayar COD (Rupiah)',
            ],
            [
                'key' => 'MARKETPLACE_COMMISSION_PERCENT',
                'value' => '10',
                'type' => 'float',
                'group' => 'commission',
                'description' => 'Persentase potongan komisi platform per pesanan selesai (%)',
            ],
            [
                'key' => 'PAYMENT_WINDOW_HOURS',
                'value' => '1',
                'type' => 'integer',
                'group' => 'payment',
                'description' => 'Batas waktu customer menyelesaikan invoice pembayaran Xendit (Jam)',
            ],
            [
                'key' => 'PAYMENT_GRACE_PERIOD_HOURS',
                'value' => '24',
                'type' => 'integer',
                'group' => 'payment',
                'description' => 'Tenggang waktu customer dapat menerbitkan ulang invoice sebelum order batal (Jam)',
            ],
            [
                'key' => 'MAX_STORE_COD_DEBT',
                'value' => '1000000',
                'type' => 'integer',
                'group' => 'payment',
                'description' => 'Batas maksimum utang komisi COD mitra toko sebelum fitur COD dinonaktifkan sementara',
            ],
            [
                'key' => 'COD_REFUND_METHOD',
                'value' => 'manual_transfer',
                'type' => 'string',
                'group' => 'refund',
                'description' => 'Metode default pengembalian dana untuk pesanan COD',
            ],
            [
                'key' => 'DISPUTE_WINDOW_HOURS',
                'value' => '24',
                'type' => 'integer',
                'group' => 'dispute',
                'description' => 'Jendela waktu customer dapat mengajukan komplain setelah order delivered (Jam)',
            ],
            [
                'key' => 'GPS_RETENTION_DAYS',
                'value' => '7',
                'type' => 'integer',
                'group' => 'courier',
                'description' => 'Masa retensi riwayat titik koordinat GPS kurir sebelum dihapus otomatis (Hari)',
            ],
            [
                'key' => 'AI_HOURLY_RATE_LIMIT',
                'value' => '20',
                'type' => 'integer',
                'group' => 'ai',
                'description' => 'Batas panggilan interaksi AI per user per jam',
            ],

            // Branding tokens from DESIGN.md
            [
                'key' => 'BRAND_PRIMARY_COLOR',
                'value' => '#1A3A5C',
                'type' => 'string',
                'group' => 'brand',
                'description' => 'Brand primary color (Deep Navy)',
            ],
            [
                'key' => 'BRAND_SECONDARY_COLOR',
                'value' => '#4A90D9',
                'type' => 'string',
                'group' => 'brand',
                'description' => 'Brand secondary / accent color (Vibrant Blue)',
            ],
            [
                'key' => 'BRAND_SUCCESS_COLOR',
                'value' => '#2ECC71',
                'type' => 'string',
                'group' => 'brand',
                'description' => 'Brand success status color (Emerald Green)',
            ],
            [
                'key' => 'BRAND_BACKGROUND_COLOR',
                'value' => '#F5F7FA',
                'type' => 'string',
                'group' => 'brand',
                'description' => 'Brand light background color (Soft Gray/Off-White)',
            ],
        ];

        foreach ($settings as $item) {
            Setting::set(
                key: $item['key'],
                value: $item['value'],
                type: $item['type'],
                group: $item['group'],
                description: $item['description']
            );
        }
    }
}
