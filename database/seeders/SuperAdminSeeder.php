<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@laundryku.test');
        $password = env('ADMIN_PASSWORD', 'admin12345');
        $name = env('ADMIN_NAME', 'Super Administrator');

        $google2fa = new Google2FA();
        $secret = env('ADMIN_2FA_SECRET', $google2fa->generateSecretKey());

        $admin = AdminUser::withoutGlobalScopes()->where('email', $email)->where('role', User::ROLE_SUPER_ADMIN)->first();

        if (!$admin) {
            $admin = new AdminUser();
            $admin->email = $email;
            $admin->role = User::ROLE_SUPER_ADMIN;
        }

        $admin->name = $name;
        $admin->password = Hash::make($password);
        $admin->is_active = true;
        $admin->google2fa_secret = $secret;
        $admin->two_factor_enabled = true;
        $admin->two_factor_confirmed_at = now();
        $admin->save();

        // Attach system roles in user_roles table if roles exist
        $superAdminRole = Role::whereIn('name', ['platform_owner', 'super_admin'])->first();
        if ($superAdminRole && !$admin->roles()->where('roles.id', $superAdminRole->id)->exists()) {
            $admin->roles()->attach($superAdminRole->id, ['assigned_at' => now()]);
        }
    }
}
