<?php

namespace Database\Seeders;

use App\Models\CourierUser;
use App\Models\CustomerUser;
use App\Models\MitraUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DefaultUsersSeeder extends Seeder
{
    /**
     * Seed default idempotent users for all isolated roles.
     */
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command?->info('DefaultUsersSeeder skipped: only executed on local/testing environment.');
            return;
        }

        // 1. Sync any existing legacy users to their correct role based on user_roles / stores / couriers
        $this->syncLegacyRoles();

        // 2. Default Customer (guard: web)
        $customerEmail = env('CUSTOMER_EMAIL', 'customer@laundryku.test');
        $customerPassword = env('CUSTOMER_PASSWORD', 'password123');
        $customer = CustomerUser::withoutGlobalScopes()
            ->where('email', $customerEmail)
            ->where('role', User::ROLE_CUSTOMER)
            ->first();

        if (!$customer) {
            $customer = new CustomerUser();
            $customer->email = $customerEmail;
            $customer->role = User::ROLE_CUSTOMER;
        }
        $customer->name = 'Budi Customer';
        $customer->password = Hash::make($customerPassword);
        $customer->is_active = true;
        $customer->email_verified_at = now();
        $customer->save();

        $customerRole = Role::where('name', 'customer')->first();
        if ($customerRole && !$customer->roles()->where('roles.id', $customerRole->id)->exists()) {
            $customer->roles()->attach($customerRole->id, ['assigned_at' => now()]);
        }

        // 3. Default Mitra / Store Admin (guard: mitra)
        $mitraEmail = env('MITRA_EMAIL', 'mitra@laundryku.test');
        $mitraPassword = env('MITRA_PASSWORD', 'password123');
        $mitra = MitraUser::withoutGlobalScopes()
            ->where('email', $mitraEmail)
            ->where('role', User::ROLE_STORE_ADMIN)
            ->first();

        if (!$mitra) {
            $mitra = new MitraUser();
            $mitra->email = $mitraEmail;
            $mitra->role = User::ROLE_STORE_ADMIN;
        }
        $mitra->name = 'Rian Mitra Toko';
        $mitra->password = Hash::make($mitraPassword);
        $mitra->is_active = true;
        $mitra->email_verified_at = now();
        $mitra->save();

        $storeOwnerRole = Role::whereIn('name', ['store_owner', 'store_admin'])->first();
        if ($storeOwnerRole && !$mitra->roles()->where('roles.id', $storeOwnerRole->id)->exists()) {
            $mitra->roles()->attach($storeOwnerRole->id, ['assigned_at' => now()]);
        }

        // 4. Default Courier (guard: kurir)
        $kurirEmail = env('KURIR_EMAIL', 'kurir@laundryku.test');
        $kurirPassword = env('KURIR_PASSWORD', 'password123');
        $kurir = CourierUser::withoutGlobalScopes()
            ->where('email', $kurirEmail)
            ->where('role', User::ROLE_COURIER)
            ->first();

        if (!$kurir) {
            $kurir = new CourierUser();
            $kurir->email = $kurirEmail;
            $kurir->role = User::ROLE_COURIER;
        }
        $kurir->name = 'Ahmad Kurir';
        $kurir->password = Hash::make($kurirPassword);
        $kurir->is_active = true;
        $kurir->email_verified_at = now();
        $kurir->save();

        $courierRole = Role::where('name', 'courier')->first();
        if ($courierRole && !$kurir->roles()->where('roles.id', $courierRole->id)->exists()) {
            $kurir->roles()->attach($courierRole->id, ['assigned_at' => now()]);
        }
    }

    /**
     * Idempotently sync role column for any legacy users that have store/courier/admin roles.
     */
    protected function syncLegacyRoles(): void
    {
        // Users owning stores or with store_owner/store_admin role in user_roles
        $storeUserIds = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->whereIn('roles.name', ['store_owner', 'store_admin'])
            ->pluck('user_roles.user_id');

        if ($storeUserIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('id', $storeUserIds)
                ->where('role', '!=', User::ROLE_STORE_ADMIN)
                ->update(['role' => User::ROLE_STORE_ADMIN]);
        }

        // Users with courier role in user_roles
        $courierUserIds = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'courier')
            ->pluck('user_roles.user_id');

        if ($courierUserIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('id', $courierUserIds)
                ->where('role', '!=', User::ROLE_COURIER)
                ->update(['role' => User::ROLE_COURIER]);
        }

        // Users with platform_owner or super_admin role in user_roles
        $adminUserIds = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->whereIn('roles.name', ['platform_owner', 'platform_admin', 'super_admin'])
            ->pluck('user_roles.user_id');

        if ($adminUserIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('id', $adminUserIds)
                ->where('role', '!=', User::ROLE_SUPER_ADMIN)
                ->update(['role' => User::ROLE_SUPER_ADMIN]);
        }
    }
}
