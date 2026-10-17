<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\ServiceCategory;
use App\Models\Store;
use App\Models\StoreService;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DevStoreSeeder extends Seeder
{
    /**
     * Seed development stores and categories (APP_ENV=local only).
     */
    public function run(): void
    {
        if (!app()->environment('local')) {
            $this->command?->info('DevStoreSeeder skipped: only executed on local environment.');
            return;
        }

        // 1. Seed Service Categories
        $categories = [
            ['name' => 'Kiloan Reguler (Cuci + Kering + Setrika)', 'slug' => 'kiloan-reguler'],
            ['name' => 'Kiloan Express / Kilat (6 - 12 Jam)', 'slug' => 'kiloan-express'],
            ['name' => 'Cuci Satuan & Dry Clean (Jas, Gaun, Bedcover)', 'slug' => 'cuci-satuan'],
            ['name' => 'Setrika Saja (Kiloan)', 'slug' => 'setrika-saja'],
            ['name' => 'Cuci Sepatu & Tas Premium', 'slug' => 'sepatu-tas'],
        ];

        $categoryModels = [];
        foreach ($categories as $cat) {
            $categoryModels[$cat['slug']] = ServiceCategory::firstOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name'], 'status' => 'active']
            );
        }

        $customerRole = Role::where('code', 'customer')->first();
        $storeAdminRole = Role::where('code', 'store_admin')->first();
        $customerRoleId = $customerRole ? $customerRole->id : 1;
        $storeAdminRoleId = $storeAdminRole ? $storeAdminRole->id : 3;

        // 2. Seed Store Admin 1: Rian Permana
        $admin1 = User::firstOrCreate(
            ['email' => 'rian.barokah@example.com'],
            [
                'name' => 'Rian Permana (Laundry Barokah)',
                'phone' => '6281220011001',
                'status' => 'active',
                'verified_at' => now(),
            ]
        );
        UserProfile::firstOrCreate(['user_id' => $admin1->id]);
        UserRole::firstOrCreate(['user_id' => $admin1->id, 'role_id' => $customerRoleId], ['status' => 'active']);

        // 3. Seed Store Admin 2: Maya Kartika
        $admin2 = User::firstOrCreate(
            ['email' => 'maya.freshclean@example.com'],
            [
                'name' => 'Maya Kartika (Fresh & Clean)',
                'phone' => '6281220011002',
                'status' => 'active',
                'verified_at' => now(),
            ]
        );
        UserProfile::firstOrCreate(['user_id' => $admin2->id]);
        UserRole::firstOrCreate(['user_id' => $admin2->id, 'role_id' => $customerRoleId], ['status' => 'active']);

        // 4. Seed Stores in West Java (Bandung)
        $stores = [
            [
                'owner' => $admin1,
                'name' => 'Laundry Barokah Dago',
                'description' => 'Jasa laundry kiloan dan satuan higienis di kawasan Dago Bandung dengan pewangi premium aromaterapi.',
                'address_text' => 'Jl. Ir. H. Juanda No. 120, Dago, Coblong, Kota Bandung, Jawa Barat 40132',
                'latitude' => -6.8852100,
                'longitude' => 107.6138200,
                'service_radius_km' => 5.00,
                'opening_hours' => [
                    'senin' => '08:00 - 20:00',
                    'selasa' => '08:00 - 20:00',
                    'rabu' => '08:00 - 20:00',
                    'kamis' => '08:00 - 20:00',
                    'jumat' => '08:00 - 20:00',
                    'sabtu' => '08:00 - 20:00',
                    'minggu' => '09:00 - 17:00',
                ],
                'services' => [
                    [
                        'category_slug' => 'kiloan-reguler',
                        'name' => 'Cuci Kering Setrika Reguler',
                        'pricing_unit' => 'kg',
                        'price_per_unit' => 7000,
                        'minimum_charge' => 21000, // 3kg min
                        'estimated_duration_hours' => 48,
                        'is_active' => true,
                    ],
                    [
                        'category_slug' => 'kiloan-express',
                        'name' => 'Cuci Kering Setrika Express (8 Jam)',
                        'pricing_unit' => 'kg',
                        'price_per_unit' => 12000,
                        'minimum_charge' => 36000,
                        'estimated_duration_hours' => 8,
                        'is_active' => true,
                    ],
                    [
                        'category_slug' => 'cuci-satuan',
                        'name' => 'Cuci Bedcover & Selimut Tebal',
                        'pricing_unit' => 'item',
                        'price_per_unit' => 25000,
                        'minimum_charge' => 0,
                        'estimated_duration_hours' => 48,
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'owner' => $admin2,
                'name' => 'Fresh & Clean Dipatiukur',
                'description' => 'Spesialis cuci express kiloan, cuci sepatu premium, dan bedcover dekat kampus UNPAD/ITHB.',
                'address_text' => 'Jl. Dipati Ukur No. 45, Lebakgede, Coblong, Kota Bandung, Jawa Barat 40132',
                'latitude' => -6.8924300,
                'longitude' => 107.6162100,
                'service_radius_km' => 4.50,
                'opening_hours' => [
                    'senin' => '07:30 - 21:00',
                    'selasa' => '07:30 - 21:00',
                    'rabu' => '07:30 - 21:00',
                    'kamis' => '07:30 - 21:00',
                    'jumat' => '07:30 - 21:00',
                    'sabtu' => '07:30 - 21:00',
                    'minggu' => '08:00 - 18:00',
                ],
                'services' => [
                    [
                        'category_slug' => 'kiloan-reguler',
                        'name' => 'Cuci Komplit Wangi Fresh',
                        'pricing_unit' => 'kg',
                        'price_per_unit' => 8000,
                        'minimum_charge' => 24000,
                        'estimated_duration_hours' => 36,
                        'is_active' => true,
                    ],
                    [
                        'category_slug' => 'kiloan-express',
                        'name' => 'Super Express 6 Jam Selesai',
                        'pricing_unit' => 'kg',
                        'price_per_unit' => 15000,
                        'minimum_charge' => 45000,
                        'estimated_duration_hours' => 6,
                        'is_active' => true,
                    ],
                    [
                        'category_slug' => 'sepatu-tas',
                        'name' => 'Deep Clean Sepatu Sneakers & Leather',
                        'pricing_unit' => 'item',
                        'price_per_unit' => 35000,
                        'minimum_charge' => 0,
                        'estimated_duration_hours' => 24,
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'owner' => $admin1,
                'name' => 'Berkah Express Buah Batu',
                'description' => 'Layanan cuci kilat wangi tahan lama di kawasan Buah Batu dan Batununggal Bandung.',
                'address_text' => 'Jl. Buah Batu No. 182, Cijagra, Lengkong, Kota Bandung, Jawa Barat 40265',
                'latitude' => -6.9405200,
                'longitude' => 107.6289300,
                'service_radius_km' => 7.00,
                'opening_hours' => [
                    'senin' => '08:00 - 21:00',
                    'selasa' => '08:00 - 21:00',
                    'rabu' => '08:00 - 21:00',
                    'kamis' => '08:00 - 21:00',
                    'jumat' => '08:00 - 21:00',
                    'sabtu' => '08:00 - 21:00',
                    'minggu' => '09:00 - 18:00',
                ],
                'services' => [
                    [
                        'category_slug' => 'kiloan-reguler',
                        'name' => 'Kiloan Reguler Bersih Higienis',
                        'pricing_unit' => 'kg',
                        'price_per_unit' => 7500,
                        'minimum_charge' => 22500,
                        'estimated_duration_hours' => 48,
                        'is_active' => true,
                    ],
                    [
                        'category_slug' => 'setrika-saja',
                        'name' => 'Setrika Uap Kiloan Rapi Wangi',
                        'pricing_unit' => 'kg',
                        'price_per_unit' => 5000,
                        'minimum_charge' => 15000,
                        'estimated_duration_hours' => 24,
                        'is_active' => true,
                    ],
                    [
                        'category_slug' => 'cuci-satuan',
                        'name' => 'Dry Clean Jas & Gaun Formal',
                        'pricing_unit' => 'item',
                        'price_per_unit' => 40000,
                        'minimum_charge' => 0,
                        'estimated_duration_hours' => 48,
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'owner' => $admin2,
                'name' => 'Klin Laundry Riau',
                'description' => 'Jasa laundry premium wangi tahan lama di pusat kota Bandung Riau.',
                'address_text' => 'Jl. LLRE Martadinata No. 55, Cihapit, Bandung Wetan, Kota Bandung, Jawa Barat 40114',
                'latitude' => -6.9078000,
                'longitude' => 107.6215000,
                'service_radius_km' => 5.00,
                'opening_hours' => [
                    'senin' => '08:00 - 20:00',
                    'selasa' => '08:00 - 20:00',
                    'rabu' => '08:00 - 20:00',
                    'kamis' => '08:00 - 20:00',
                    'jumat' => '08:00 - 20:00',
                    'sabtu' => '08:00 - 20:00',
                    'minggu' => '09:00 - 18:00',
                ],
                'services' => [
                    [
                        'category_slug' => 'kiloan-reguler',
                        'name' => 'Cuci Kering Wangi Premium',
                        'pricing_unit' => 'kg',
                        'price_per_unit' => 8500,
                        'minimum_charge' => 25500,
                        'estimated_duration_hours' => 24,
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'owner' => $admin1,
                'name' => 'Clean & Bright Antapani',
                'description' => 'Outlet laundry kilat dan bersih higienis di perumahan Antapani Bandung.',
                'address_text' => 'Jl. Purwakarta No. 12, Antapani Tengah, Antapani, Kota Bandung, Jawa Barat 40291',
                'latitude' => -6.9142000,
                'longitude' => 107.6543000,
                'service_radius_km' => 6.00,
                'opening_hours' => [
                    'senin' => '07:00 - 21:00',
                    'selasa' => '07:00 - 21:00',
                    'rabu' => '07:00 - 21:00',
                    'kamis' => '07:00 - 21:00',
                    'jumat' => '07:00 - 21:00',
                    'sabtu' => '07:00 - 21:00',
                    'minggu' => '08:00 - 20:00',
                ],
                'services' => [
                    [
                        'category_slug' => 'kiloan-express',
                        'name' => 'Cuci Lipat Kilat 12 Jam',
                        'pricing_unit' => 'kg',
                        'price_per_unit' => 9500,
                        'minimum_charge' => 28500,
                        'estimated_duration_hours' => 12,
                        'is_active' => true,
                    ],
                ],
            ],
        ];

        foreach ($stores as $s) {
            $store = Store::firstOrCreate(
                ['name' => $s['name'], 'owner_user_id' => $s['owner']->id],
                [
                    'description' => $s['description'],
                    'address_text' => $s['address_text'],
                    'latitude' => $s['latitude'],
                    'longitude' => $s['longitude'],
                    'service_radius_km' => $s['service_radius_km'],
                    'opening_hours' => $s['opening_hours'],
                    'approval_status' => 'approved',
                    'operational_status' => 'active',
                    'approved_by' => null,
                    'approved_at' => now(),
                ]
            );

            UserRole::firstOrCreate(
                [
                    'user_id' => $s['owner']->id,
                    'role_id' => $storeAdminRoleId,
                    'store_id' => $store->id,
                ],
                [
                    'status' => 'active',
                ]
            );

            // Seed store services
            if (!empty($s['services'])) {
                foreach ($s['services'] as $svc) {
                    $catId = $categoryModels[$svc['category_slug']]->id ?? null;
                    if ($catId) {
                        StoreService::firstOrCreate(
                            [
                                'store_id' => $store->id,
                                'name' => $svc['name'],
                            ],
                            [
                                'category_id' => $catId,
                                'pricing_unit' => $svc['pricing_unit'],
                                'price_per_unit' => $svc['price_per_unit'],
                                'minimum_charge' => $svc['minimum_charge'],
                                'estimated_duration_hours' => $svc['estimated_duration_hours'],
                                'is_active' => $svc['is_active'],
                            ]
                        );
                    }
                }
            }
        }
    }
}
