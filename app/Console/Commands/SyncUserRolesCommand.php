<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncUserRolesCommand extends Command
{
    protected $signature = 'auth:sync-roles';
    protected $description = 'Sinkronkan role pengguna dari relasi stores/user_roles dan pulihkan akun lama secara idempoten';

    public function handle(): int
    {
        $this->info('Memulai sinkronisasi role akun (Idempotent)...');

        // 1. Pulihkan akun lama dari storage jika belum ada di database
        $this->restoreLegacyAccounts();

        // 2. Sinkronkan users.role berdasarkan kepemilikan toko (stores.owner_user_id)
        if (Schema::hasTable('stores') && Schema::hasColumn('stores', 'owner_user_id')) {
            $storeOwnerIds = DB::table('stores')
                ->whereNotNull('owner_user_id')
                ->pluck('owner_user_id')
                ->unique();

            foreach ($storeOwnerIds as $userId) {
                DB::table('users')
                    ->where('id', $userId)
                    ->update(['role' => User::ROLE_STORE_ADMIN]);
            }
            $this->info('Sinkronisasi pemilik toko selesai: ' . count($storeOwnerIds) . ' akun.');
        }

        // 3. Sinkronkan users.role berdasarkan tabel user_roles
        if (Schema::hasTable('user_roles') && Schema::hasTable('roles')) {
            // Mitra / Store Admin
            $storeRoles = Role::whereIn('name', ['store_owner', 'store_admin'])->pluck('id');
            if ($storeRoles->isNotEmpty()) {
                $mitraUserIds = DB::table('user_roles')->whereIn('role_id', $storeRoles)->pluck('user_id');
                DB::table('users')->whereIn('id', $mitraUserIds)->update(['role' => User::ROLE_STORE_ADMIN]);
            }

            // Courier
            $courierRoles = Role::where('name', 'courier')->pluck('id');
            if ($courierRoles->isNotEmpty()) {
                $courierUserIds = DB::table('user_roles')->whereIn('role_id', $courierRoles)->pluck('user_id');
                DB::table('users')->whereIn('id', $courierUserIds)->update(['role' => User::ROLE_COURIER]);
            }

            // Super Admin
            $adminRoles = Role::whereIn('name', ['platform_owner', 'platform_admin', 'super_admin'])->pluck('id');
            if ($adminRoles->isNotEmpty()) {
                $adminUserIds = DB::table('user_roles')->whereIn('role_id', $adminRoles)->pluck('user_id');
                DB::table('users')->whereIn('id', $adminUserIds)->update(['role' => User::ROLE_SUPER_ADMIN]);
            }
        }

        // 4. Defaultkan akun dengan role null menjadi customer
        DB::table('users')->whereNull('role')->update(['role' => User::ROLE_CUSTOMER]);

        // 5. Pastikan semua user memiliki record di user_roles yang sesuai
        $this->syncUserRolesPivot();

        $this->info('Sinkronisasi role selesai 100%.');
        return self::SUCCESS;
    }

    protected function restoreLegacyAccounts(): void
    {
        $jsonPath = storage_path('parsed_old_users.json');
        if (!file_exists($jsonPath)) {
            return;
        }

        $oldUsers = json_decode(file_get_contents($jsonPath), true);
        if (!is_array($oldUsers)) {
            return;
        }

        $customerRole = Role::where('name', 'customer')->first();
        $storeRole = Role::whereIn('name', ['store_owner', 'store_admin'])->first();
        $courierRole = Role::where('name', 'courier')->first();
        $adminRole = Role::whereIn('name', ['platform_owner', 'super_admin'])->first();

        foreach ($oldUsers as $u) {
            $email = $u['email'] ?? null;
            if ($email === 'NULL' || empty($email)) {
                continue;
            }

            $phone = ($u['phone'] !== 'NULL' && !empty($u['phone'])) ? $u['phone'] : null;
            $passwordHash = ($u['password_hash'] !== 'NULL' && !empty($u['password_hash'])) ? $u['password_hash'] : null;

            // Tentukan role awal dari active_mode atau email
            $role = User::ROLE_CUSTOMER;
            if (($u['active_mode'] ?? '') === 'courier') {
                $role = User::ROLE_COURIER;
            } elseif (($u['active_mode'] ?? '') === 'store') {
                $role = User::ROLE_STORE_ADMIN;
            } elseif (in_array(strtolower($email), ['admin@laundryku.com', 'admin@laundryku.test', 'superadmin@laundryku.test'], true)) {
                $role = User::ROLE_SUPER_ADMIN;
            }

            $existing = DB::table('users')->where('email', $email)->first();
            if (!$existing) {
                $newId = DB::table('users')->insertGetId([
                    'name' => $u['name'] ?? 'User',
                    'email' => $email,
                    'phone' => $phone,
                    'role' => $role,
                    'password' => $passwordHash ?? '', // Jangan ubah password hash!
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Hubungkan ke user_roles
                $roleId = match ($role) {
                    User::ROLE_SUPER_ADMIN => $adminRole?->id,
                    User::ROLE_STORE_ADMIN => $storeRole?->id,
                    User::ROLE_COURIER => $courierRole?->id,
                    default => $customerRole?->id,
                };

                if ($roleId) {
                    DB::table('user_roles')->insertOrIgnore([
                        'user_id' => $newId,
                        'role_id' => $roleId,
                        'assigned_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } else {
                // Jangan sentuh password yang sudah ada! Hanya update phone jika kosong & role jika masih customer
                $updateData = [];
                if (empty($existing->phone) && $phone) {
                    $updateData['phone'] = $phone;
                }
                if ($role !== User::ROLE_CUSTOMER && $existing->role === User::ROLE_CUSTOMER) {
                    $updateData['role'] = $role;
                }
                if (!empty($updateData)) {
                    DB::table('users')->where('id', $existing->id)->update($updateData);
                }
            }
        }
    }

    protected function syncUserRolesPivot(): void
    {
        $customerRole = Role::where('name', 'customer')->first();
        $storeRole = Role::whereIn('name', ['store_owner', 'store_admin'])->first();
        $courierRole = Role::where('name', 'courier')->first();
        $adminRole = Role::whereIn('name', ['platform_owner', 'super_admin'])->first();

        $users = DB::table('users')->get();
        foreach ($users as $user) {
            $targetRoleId = match ($user->role) {
                User::ROLE_SUPER_ADMIN => $adminRole?->id,
                User::ROLE_STORE_ADMIN => $storeRole?->id,
                User::ROLE_COURIER => $courierRole?->id,
                default => $customerRole?->id,
            };

            if ($targetRoleId) {
                $hasPivot = DB::table('user_roles')
                    ->where('user_id', $user->id)
                    ->where('role_id', $targetRoleId)
                    ->exists();

                if (!$hasPivot) {
                    DB::table('user_roles')->insertOrIgnore([
                        'user_id' => $user->id,
                        'role_id' => $targetRoleId,
                        'assigned_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
