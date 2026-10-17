<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['id' => 1, 'code' => 'customer', 'name' => 'Customer', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'courier', 'name' => 'Kurir', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'code' => 'store_admin', 'name' => 'Admin Toko', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'code' => 'super_admin', 'name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['code' => $role['code']],
                [
                    'name' => $role['name'],
                    'updated_at' => $role['updated_at'],
                    'created_at' => $role['created_at'],
                ]
            );
        }
    }
}
