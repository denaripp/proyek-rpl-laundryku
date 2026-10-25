<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DevDemoDataSeeder extends Seeder
{
    /**
     * Seed example demo data for local development testing.
     * Idempotent (safe to re-run with updateOrCreate / updateOrInsert).
     * Strictly restricted to the local environment.
     */
    public function run(): void
    {
        if (!app()->environment('local')) {
            $this->command?->warn('DevDemoDataSeeder: Hanya boleh dijalankan di environment local!');
            return;
        }

        $this->command?->info('Menjalankan DevDemoDataSeeder untuk environment local...');

        // 1. Dapatkan akun utama
        $mitra = User::where('email', 'mitra@laundryku.test')->first();
        $customer = User::where('email', 'customer@laundryku.test')->first();
        $kurir = User::where('email', 'kurir@laundryku.test')->first();

        if (!$mitra || !$customer || !$kurir) {
            $this->command?->error('Gagal: Akun utama (mitra, customer, kurir) belum ada di tabel users!');
            return;
        }

        // Ambil nominal platform fee resmi dari tabel settings (default: 1000)
        $platformFeeSetting = (float) (DB::table('settings')->where('key', 'PLATFORM_FEE')->value('value') ?? 1000.00);

        // 2. Buat / Update 2 Toko Mitra
        $operationalHours1 = [
            'senin' => '08:00 - 20:00',
            'selasa' => '08:00 - 20:00',
            'rabu' => '08:00 - 20:00',
            'kamis' => '08:00 - 20:00',
            'jumat' => '08:00 - 20:00',
            'sabtu' => '08:00 - 20:00',
            'minggu' => '09:00 - 17:00',
        ];

        $store1 = Store::updateOrCreate(
            ['slug' => 'laundry-berkah-mitra'],
            [
                'owner_user_id' => $mitra->id,
                'name' => 'Laundry Berkah Mitra',
                'phone' => '081220011001',
                'whatsapp' => '6281220011001',
                'address' => 'Jl. Dago Asri No. 12',
                'rt_rw' => '02/05',
                'kelurahan' => 'Dago',
                'kecamatan' => 'Coblong',
                'city' => 'Kota Bandung',
                'province' => 'Jawa Barat',
                'postal_code' => '40135',
                'latitude' => -6.8852100,
                'longitude' => 107.6138200,
                'is_active' => true,
                'status' => 'approved',
                'cod_enabled' => true,
                'operational_hours' => $operationalHours1,
                'service_radius_km' => 7.50,
                'minimum_order_amount' => 15000,
                'pickup_fee' => 3000,
                'delivery_fee' => 3000,
                'commission_rate' => 10.00,
                'average_rating' => 5.00,
                'review_count' => 1,
                'approved_at' => Carbon::now('UTC')->subDays(5),
            ]
        );

        $operationalHours2 = [
            'senin' => '07:30 - 21:00',
            'selasa' => '07:30 - 21:00',
            'rabu' => '07:30 - 21:00',
            'kamis' => '07:30 - 21:00',
            'jumat' => '07:30 - 21:00',
            'sabtu' => '07:30 - 21:00',
            'minggu' => '08:00 - 18:00',
        ];

        $store2 = Store::updateOrCreate(
            ['slug' => 'klin-laundry-express'],
            [
                'owner_user_id' => $mitra->id,
                'name' => 'Klin Laundry Express',
                'phone' => '081220011002',
                'whatsapp' => '6281220011002',
                'address' => 'Jl. Dipati Ukur No. 45',
                'rt_rw' => '04/02',
                'kelurahan' => 'Lebakgede',
                'kecamatan' => 'Coblong',
                'city' => 'Kota Bandung',
                'province' => 'Jawa Barat',
                'postal_code' => '40132',
                'latitude' => -6.8924300,
                'longitude' => 107.6162100,
                'is_active' => true,
                'status' => 'approved',
                'cod_enabled' => true,
                'operational_hours' => $operationalHours2,
                'service_radius_km' => 5.00,
                'minimum_order_amount' => 20000,
                'pickup_fee' => 2500,
                'delivery_fee' => 2500,
                'commission_rate' => 10.00,
                'average_rating' => 4.80,
                'review_count' => 8,
                'approved_at' => Carbon::now('UTC')->subDays(5),
            ]
        );

        // Hubungkan mitra ke tabel store_staff
        DB::table('store_staff')->updateOrInsert(
            ['store_id' => $store1->id, 'user_id' => $mitra->id],
            [
                'permissions' => json_encode(['manage_orders', 'manage_services', 'manage_settings']),
                'is_active' => 1,
                'accepted_at' => Carbon::now('UTC')->subDays(5),
                'updated_at' => Carbon::now('UTC')->subDays(5),
                'created_at' => Carbon::now('UTC')->subDays(5),
            ]
        );
        DB::table('store_staff')->updateOrInsert(
            ['store_id' => $store2->id, 'user_id' => $mitra->id],
            [
                'permissions' => json_encode(['manage_orders', 'manage_services', 'manage_settings']),
                'is_active' => 1,
                'accepted_at' => Carbon::now('UTC')->subDays(5),
                'updated_at' => Carbon::now('UTC')->subDays(5),
                'created_at' => Carbon::now('UTC')->subDays(5),
            ]
        );

        // 3. Master Layanan (Services)
        $servicesDefs = [
            [
                'name' => 'Cuci Komplit',
                'description' => 'Cuci, kering, dan setrika rapi wangi siap pakai.',
                'icon' => 'sparkles',
            ],
            [
                'name' => 'Cuci Kering',
                'description' => 'Cuci dan pengeringan higienis tanpa setrika.',
                'icon' => 'dry',
            ],
            [
                'name' => 'Setrika Saja',
                'description' => 'Setrika uap licin dan rapi untuk pakaian bersih Anda.',
                'icon' => 'iron',
            ],
            [
                'name' => 'Dry Clean',
                'description' => 'Pembersihan khusus gaun, jas, batik, dan busana formal.',
                'icon' => 'coat',
            ],
            [
                'name' => 'Cuci Bedcover',
                'description' => 'Pencucian khusus selimut tebal, sprei, dan bedcover jumbo.',
                'icon' => 'bed',
            ],
        ];

        $services = [];
        foreach ($servicesDefs as $def) {
            $services[$def['name']] = Service::updateOrCreate(
                ['name' => $def['name']],
                [
                    'description' => $def['description'],
                    'icon' => $def['icon'],
                    'is_active' => true,
                ]
            );
        }

        // 4. Harga Layanan (Service Prices) per Toko
        $store1Prices = [
            'Cuci Komplit' => ['price' => 7000.00, 'unit' => 'kg'],
            'Cuci Kering' => ['price' => 5000.00, 'unit' => 'kg'],
            'Setrika Saja' => ['price' => 4000.00, 'unit' => 'kg'],
            'Dry Clean' => ['price' => 25000.00, 'unit' => 'item'],
            'Cuci Bedcover' => ['price' => 20000.00, 'unit' => 'item'],
        ];

        $store2Prices = [
            'Cuci Komplit' => ['price' => 8000.00, 'unit' => 'kg'],
            'Cuci Kering' => ['price' => 6000.00, 'unit' => 'kg'],
            'Setrika Saja' => ['price' => 4500.00, 'unit' => 'kg'],
            'Dry Clean' => ['price' => 30000.00, 'unit' => 'item'],
            'Cuci Bedcover' => ['price' => 25000.00, 'unit' => 'item'],
        ];

        foreach ($store1Prices as $sName => $pData) {
            DB::table('service_prices')->updateOrInsert(
                ['store_id' => $store1->id, 'service_id' => $services[$sName]->id],
                [
                    'price_per_unit' => $pData['price'],
                    'unit' => $pData['unit'],
                    'is_active' => true,
                    'updated_at' => Carbon::now('UTC')->subDays(5),
                    'created_at' => Carbon::now('UTC')->subDays(5),
                ]
            );
        }

        foreach ($store2Prices as $sName => $pData) {
            DB::table('service_prices')->updateOrInsert(
                ['store_id' => $store2->id, 'service_id' => $services[$sName]->id],
                [
                    'price_per_unit' => $pData['price'],
                    'unit' => $pData['unit'],
                    'is_active' => true,
                    'updated_at' => Carbon::now('UTC')->subDays(5),
                    'created_at' => Carbon::now('UTC')->subDays(5),
                ]
            );
        }

        // 5. Profil Kurir (couriers) untuk kurir@laundryku.test
        $courierProfile = DB::table('couriers')->where('user_id', $kurir->id)->first();
        if (!$courierProfile) {
            $courierId = DB::table('couriers')->insertGetId([
                'user_id' => $kurir->id,
                'vehicle_type' => 'motorcycle',
                'vehicle_plate' => 'D 1234 ABC',
                'is_verified' => 1,
                'is_available' => 1,
                'current_latitude' => -6.8850000,
                'current_longitude' => 107.6140000,
                'last_location_update' => Carbon::now('UTC'),
                'rating' => 4.95,
                'total_tasks' => 5,
                'completed_tasks' => 4,
                'created_at' => Carbon::now('UTC')->subDays(5),
                'updated_at' => Carbon::now('UTC'),
            ]);
        } else {
            $courierId = $courierProfile->id;
            DB::table('couriers')->where('id', $courierId)->update([
                'is_available' => 1,
                'is_verified' => 1,
                'updated_at' => Carbon::now('UTC'),
            ]);
        }

        // 6. Buat 5 Pesanan Customer dengan Status Beragam (Semua status sah di App\Enums\OrderStatus)
        // Waktu kejadian (UTC)
        $tNow = Carbon::now('UTC');

        // Pesanan 1: Sedang Dicuci & Diproses (OrderStatus::PROCESSING -> 'processing')
        $tOrder1 = $tNow->copy()->subHours(4);
        $order1 = Order::updateOrCreate(
            ['order_number' => 'ORD-20261018-PROC01'],
            [
                'customer_id' => $customer->id,
                'store_id' => $store1->id,
                'courier_id' => $kurir->id,
                'status' => OrderStatus::PROCESSING->value,
                'payment_status' => 'paid',
                'payment_method' => 'qris',
                'estimated_weight' => 3.50,
                'actual_weight' => 3.50,
                'weight' => 3.50,
                'estimated_price' => 24500.00,
                'final_price' => 24500.00,
                'total_price' => 24500.00,
                'pickup_fee' => 3000.00,
                'delivery_fee' => 3000.00,
                'platform_fee' => $platformFeeSetting, // Sumber: tabel settings PLATFORM_FEE
                'total_amount' => 24500.00 + 3000.00 + 3000.00 + $platformFeeSetting, // 31.500
                'order_date' => $tOrder1,
                'created_at' => $tOrder1,
                'paid_at' => $tOrder1->copy()->addHour(),
                'weighed_at' => $tOrder1->copy()->addHour(),
                'received_by_store_at' => $tOrder1->copy()->addHours(2),
                'processing_started_at' => $tOrder1->copy()->addHours(3),
                'customer_notes' => 'Tolong pisahkan pakaian putih dan gunakan pelembut ekstra.',
            ]
        );

        DB::table('order_items')->updateOrInsert(
            ['order_id' => $order1->id, 'service_id' => $services['Cuci Komplit']->id],
            [
                'quantity' => 3.50,
                'unit' => 'kg',
                'price_per_unit' => 7000.00,
                'total_price' => 24500.00,
                'created_at' => $tOrder1,
                'updated_at' => $tOrder1,
            ]
        );

        DB::table('order_status_history')->updateOrInsert(
            ['order_id' => $order1->id, 'status' => OrderStatus::PROCESSING->value],
            [
                'previous_status' => OrderStatus::PAID->value,
                'actor_id' => $mitra->id,
                'actor_role' => 'store_admin',
                'reason' => 'Pakaian telah dipilah dan dimasukkan ke mesin cuci',
                'created_at' => $tOrder1->copy()->addHours(3),
            ]
        );

        // Pembayaran untuk Pesanan 1
        DB::table('payments')->updateOrInsert(
            ['order_id' => $order1->id],
            [
                'payment_method' => 'qris',
                'amount' => 31500.00,
                'status' => 'paid',
                'paid_at' => $tOrder1->copy()->addHour(),
                'created_at' => $tOrder1,
                'updated_at' => $tOrder1->copy()->addHour(),
            ]
        );

        // Pesanan 2: Selesai dan Lunas (OrderStatus::COMPLETED -> 'completed') — Diberi Review 5 Bintang (Tanpa Komplain)
        $tOrder2 = $tNow->copy()->subDays(2);
        $order2 = Order::updateOrCreate(
            ['order_number' => 'ORD-20261018-DONE02'],
            [
                'customer_id' => $customer->id,
                'store_id' => $store1->id,
                'courier_id' => $kurir->id,
                'status' => OrderStatus::COMPLETED->value,
                'payment_status' => 'paid',
                'payment_method' => 'bank_transfer',
                'estimated_weight' => 5.00,
                'actual_weight' => 5.00,
                'weight' => 5.00,
                'estimated_price' => 35000.00,
                'final_price' => 35000.00,
                'total_price' => 35000.00,
                'pickup_fee' => 3000.00,
                'delivery_fee' => 3000.00,
                'platform_fee' => $platformFeeSetting, // Rp 1.000
                'total_amount' => 35000.00 + 3000.00 + 3000.00 + $platformFeeSetting, // 42.000
                'order_date' => $tOrder2,
                'created_at' => $tOrder2,
                'paid_at' => $tOrder2->copy()->addHour(),
                'processing_started_at' => $tOrder2->copy()->addHours(3),
                'ready_at' => $tOrder2->copy()->addHours(20),
                'delivered_at' => $tOrder2->copy()->addHours(24),
                'completed_at' => $tOrder2->copy()->addHours(25),
                'customer_notes' => 'Diantar sebelum sore jam 4.',
            ]
        );

        DB::table('order_items')->updateOrInsert(
            ['order_id' => $order2->id, 'service_id' => $services['Cuci Komplit']->id],
            [
                'quantity' => 5.00,
                'unit' => 'kg',
                'price_per_unit' => 7000.00,
                'total_price' => 35000.00,
                'created_at' => $tOrder2,
                'updated_at' => $tOrder2,
            ]
        );

        DB::table('payments')->updateOrInsert(
            ['order_id' => $order2->id],
            [
                'payment_method' => 'bank_transfer',
                'amount' => 42000.00,
                'status' => 'paid',
                'paid_at' => $tOrder2->copy()->addHour(),
                'created_at' => $tOrder2,
                'updated_at' => $tOrder2->copy()->addHour(),
            ]
        );

        // Review 5 Bintang HANYA di Pesanan 2
        DB::table('reviews')->updateOrInsert(
            ['order_id' => $order2->id],
            [
                'customer_id' => $customer->id,
                'store_id' => $store1->id,
                'rating' => 5,
                'comment' => 'Pencucian sangat rapi dan tepat waktu!',
                'is_visible' => 1,
                'created_at' => $tOrder2->copy()->addHours(26),
                'updated_at' => $tOrder2->copy()->addHours(26),
            ]
        );

        // Pesanan 3: Belum Dibayar (OrderStatus::PAYMENT_REQUIRED -> 'payment_required')
        $tOrder3 = $tNow->copy()->subMinutes(40);
        $order3 = Order::updateOrCreate(
            ['order_number' => 'ORD-20261018-PAYREQ'],
            [
                'customer_id' => $customer->id,
                'store_id' => $store2->id,
                'status' => OrderStatus::PAYMENT_REQUIRED->value,
                'payment_status' => 'unpaid',
                'payment_method' => 'qris',
                'estimated_weight' => 2.00,
                'actual_weight' => 2.00,
                'weight' => 2.00,
                'estimated_price' => 12000.00,
                'final_price' => 12000.00,
                'total_price' => 12000.00,
                'pickup_fee' => 2500.00,
                'delivery_fee' => 2500.00,
                'platform_fee' => $platformFeeSetting, // Rp 1.000
                'total_amount' => 12000.00 + 2500.00 + 2500.00 + $platformFeeSetting, // 18.000
                'order_date' => $tOrder3,
                'created_at' => $tOrder3,
                'weighed_at' => $tOrder3->copy()->addMinutes(15),
                'customer_notes' => 'Cuci kering saja.',
            ]
        );

        DB::table('order_items')->updateOrInsert(
            ['order_id' => $order3->id, 'service_id' => $services['Cuci Kering']->id],
            [
                'quantity' => 2.00,
                'unit' => 'kg',
                'price_per_unit' => 6000.00,
                'total_price' => 12000.00,
                'created_at' => $tOrder3,
                'updated_at' => $tOrder3,
            ]
        );

        DB::table('payments')->updateOrInsert(
            ['order_id' => $order3->id],
            [
                'payment_method' => 'qris',
                'amount' => 18000.00,
                'status' => 'pending',
                'created_at' => $tOrder3,
                'updated_at' => $tOrder3,
            ]
        );

        // Pesanan 4: Selesai dan Lunas, dengan Komplain Aktif (OrderStatus::COMPLETED -> 'completed') — Tanpa Review
        $tOrder4 = $tNow->copy()->subDays(3);
        $order4 = Order::updateOrCreate(
            ['order_number' => 'ORD-20261018-CMPL04'],
            [
                'customer_id' => $customer->id,
                'store_id' => $store1->id,
                'courier_id' => $kurir->id,
                'status' => OrderStatus::COMPLETED->value,
                'payment_status' => 'paid',
                'payment_method' => 'bank_transfer',
                'estimated_weight' => 2.00,
                'actual_weight' => 2.00,
                'weight' => 2.00,
                'estimated_price' => 14000.00,
                'final_price' => 14000.00,
                'total_price' => 14000.00,
                'pickup_fee' => 3000.00,
                'delivery_fee' => 3000.00,
                'platform_fee' => $platformFeeSetting, // Rp 1.000
                'total_amount' => 14000.00 + 3000.00 + 3000.00 + $platformFeeSetting, // 21.000
                'order_date' => $tOrder4,
                'created_at' => $tOrder4,
                'paid_at' => $tOrder4->copy()->addHour(),
                'processing_started_at' => $tOrder4->copy()->addHours(3),
                'ready_at' => $tOrder4->copy()->addHours(15),
                'delivered_at' => $tOrder4->copy()->addHours(20),
                'completed_at' => $tOrder4->copy()->addHours(21),
                'customer_notes' => 'Pakaian kantor.',
            ]
        );

        DB::table('order_items')->updateOrInsert(
            ['order_id' => $order4->id, 'service_id' => $services['Cuci Komplit']->id],
            [
                'quantity' => 2.00,
                'unit' => 'kg',
                'price_per_unit' => 7000.00,
                'total_price' => 14000.00,
                'created_at' => $tOrder4,
                'updated_at' => $tOrder4,
            ]
        );

        DB::table('payments')->updateOrInsert(
            ['order_id' => $order4->id],
            [
                'payment_method' => 'bank_transfer',
                'amount' => 21000.00,
                'status' => 'paid',
                'paid_at' => $tOrder4->copy()->addHour(),
                'created_at' => $tOrder4,
                'updated_at' => $tOrder4->copy()->addHour(),
            ]
        );

        // Komplain/Dispute diletakkan di Pesanan 4 (Bukan di Pesanan 2)
        DB::table('disputes')->updateOrInsert(
            ['complaint_number' => 'CMP-20261018-001'],
            [
                'order_id' => $order4->id,
                'opened_by' => $customer->id,
                'reason' => 'Pewangi cucian kurang tahan lama',
                'description' => 'Cucian rapi dan bersih, namun aroma wanginya kurang sesuai pilihan aroma lavender.',
                'status' => 'under_review',
                'sla_due_at' => $tNow->copy()->addDays(2),
                'created_at' => $tOrder4->copy()->addHours(22),
                'updated_at' => $tOrder4->copy()->addHours(22),
            ]
        );

        // Pesanan 5: Dibatalkan (OrderStatus::CANCELLED -> 'cancelled')
        $tOrder5 = $tNow->copy()->subDays(1);
        $order5 = Order::updateOrCreate(
            ['order_number' => 'ORD-20261018-CANC05'],
            [
                'customer_id' => $customer->id,
                'store_id' => $store2->id,
                'status' => OrderStatus::CANCELLED->value,
                'payment_status' => 'cancelled',
                'payment_method' => 'qris',
                'estimated_weight' => 3.00,
                'actual_weight' => null,
                'weight' => 3.00,
                'estimated_price' => 18000.00,
                'final_price' => null,
                'total_price' => 18000.00,
                'pickup_fee' => 2500.00,
                'delivery_fee' => 2500.00,
                'platform_fee' => $platformFeeSetting,
                'total_amount' => 24000.00,
                'order_date' => $tOrder5,
                'created_at' => $tOrder5,
                'cancelled_at' => $tOrder5->copy()->addMinutes(30),
                'cancelled_by' => $customer->id,
                'cancellation_reason' => 'Pelanggan membatalkan pesanan sebelum penjemputan oleh kurir.',
                'customer_notes' => 'Batal karena bepergian mendadak.',
            ]
        );

        DB::table('order_items')->updateOrInsert(
            ['order_id' => $order5->id, 'service_id' => $services['Cuci Kering']->id],
            [
                'quantity' => 3.00,
                'unit' => 'kg',
                'price_per_unit' => 6000.00,
                'total_price' => 18000.00,
                'created_at' => $tOrder5,
                'updated_at' => $tOrder5,
            ]
        );

        // 7. Tugas Kurir untuk kurir@laundryku.test (status sah di enum courier_tasks: picked_up)
        DB::table('courier_tasks')->updateOrInsert(
            ['order_id' => $order1->id, 'courier_id' => $courierId, 'task_type' => 'pickup'],
            [
                'status' => 'picked_up',
                'assigned_at' => $tOrder1->copy()->addHour(),
                'accepted_at' => $tOrder1->copy()->addHour()->addMinutes(5),
                'arrived_at' => $tOrder1->copy()->addHours(1)->addMinutes(30),
                'completed_at' => $tOrder1->copy()->addHours(2),
                'distance_km' => 3.20,
                'duration_minutes' => 25,
                'created_at' => $tOrder1->copy()->addHour(),
                'updated_at' => $tOrder1->copy()->addHours(2),
            ]
        );

        $this->command?->info('DevDemoDataSeeder selesai dieksekusi dengan sukses.');
    }
}
