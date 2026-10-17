<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure Super Admin role exists
        $superAdminRole = Role::firstOrCreate(
            ['code' => 'super_admin'],
            ['name' => 'Super Admin']
        );

        // 2. Create or update the initial Super Admin account (FR-ADMIN-01, US-ADMIN-01)
        // PERINGATAN KEAMANAN: Kredensial default (username: admin, password: admin123) ini HANYA
        // ditujukan untuk environment development lokal dan WAJIB diganti dengan kredensial rahasia yang kuat sebelum deployment produksi.
        $username = env('ADMIN_USERNAME', 'admin');
        $email = env('ADMIN_EMAIL', env('SUPERADMIN_EMAIL', 'admin@laundryku.id'));
        $name = env('ADMIN_NAME', env('SUPERADMIN_NAME', 'Super Administrator'));
        $phone = env('ADMIN_PHONE', env('SUPERADMIN_PHONE', '+6281100000001'));
        $password = env('ADMIN_PASSWORD', env('SUPERADMIN_PASSWORD', 'admin123'));

        $superAdmin = User::where('username', $username)
            ->orWhere('email', $email)
            ->orWhere('phone', $phone)
            ->first();

        if ($superAdmin) {
            $superAdmin->update([
                'username' => $username,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'verified_at' => $superAdmin->verified_at ?? now(),
                'status' => 'active',
                'password' => Hash::make($password),
            ]);
        } else {
            $superAdmin = User::create([
                'username' => $username,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'verified_at' => now(),
                'status' => 'active',
                'password' => Hash::make($password),
            ]);
        }

        \App\Models\UserRole::firstOrCreate(
            ['user_id' => $superAdmin->id, 'role_id' => $superAdminRole->id],
            ['status' => 'active']
        );

        // 3. Default Platform Commission Setting
        PlatformSetting::firstOrCreate(
            ['key' => 'platform_commission_flat'],
            [
                'value_json' => ['amount' => 1000],
                'description' => 'Besaran komisi platform flat per order selesai (Rupiah)',
                'updated_by' => $superAdmin->id,
            ]
        );

        // 4. Default Service Categories Master Data
        $categories = [
            ['name' => 'Kiloan Reguler', 'slug' => 'kiloan-reguler', 'status' => 'active'],
            ['name' => 'Kiloan Express', 'slug' => 'kiloan-express', 'status' => 'active'],
            ['name' => 'Satuan Pakaian & Jas', 'slug' => 'satuan-pakaian-jas', 'status' => 'active'],
            ['name' => 'Satuan Bedcover & Linen', 'slug' => 'satuan-bedcover-linen', 'status' => 'active'],
            ['name' => 'Sepatu, Tas & Karpet', 'slug' => 'sepatu-tas-karpet', 'status' => 'active'],
        ];

        foreach ($categories as $cat) {
            ServiceCategory::firstOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name'], 'status' => $cat['status']]
            );
        }
    }
}
