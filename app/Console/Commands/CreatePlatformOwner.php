<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Role;
use App\Models\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreatePlatformOwner extends Command
{
    protected $signature = 'platform:owner {email} {name} {--password=}';
    protected $description = 'Create a Platform Owner user (provisioning internal only)';

    public function handle()
    {
        $email = $this->argument('email');
        $name = $this->argument('name');
        $password = $this->option('password');
        if (empty($password)) {
            $password = $this->secret('Masukkan password untuk Platform Owner');
        }

        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'password' => $password,
        ], [
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return 1;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $role = Role::where('name', 'platform_owner')->first() ?? Role::firstOrCreate(['name' => 'platform_owner'], [
            'display_name' => 'Platform Owner',
            'description' => 'Owner of the entire platform',
            'is_system' => true,
        ]);

        UserRole::firstOrCreate([
            'user_id' => $user->id,
            'role_id' => $role->id,
        ], [
            'assigned_at' => now(),
        ]);

        $this->info('Platform Owner created successfully!');
        $this->table(['ID', 'Name', 'Email'], [
            [$user->id, $user->name, $user->email],
        ]);

        return 0;
    }
}