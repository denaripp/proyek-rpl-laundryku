<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MitraChangeEmailCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_mitra_change_email_success_and_preserves_password_hash(): void
    {
        $oldEmail = 'bhevinofficialsotre@gmail.com';
        $newEmail = 'bhevinofficialstore@gmail.com';
        $rawPassword = 'PasswordMitra123!';
        $initialHash = Hash::make($rawPassword);

        $user = User::create([
            'name' => 'Bhevin',
            'email' => $oldEmail,
            'phone' => '081234567890',
            'password' => $initialHash,
            'role' => User::ROLE_STORE_ADMIN,
            'is_active' => true,
        ]);

        $this->artisan('mitra:change-email', ['old' => $oldEmail, 'new' => $newEmail])
            ->expectsConfirmation(
                "Apakah Anda yakin ingin memperbarui email user #{$user->id} (Bhevin) dari '{$oldEmail}' menjadi '{$newEmail}'?",
                'yes'
            )
            ->expectsOutputToContain("Email user #{$user->id} diperbarui.")
            ->assertExitCode(0);

        $user->refresh();

        // Email berubah menjadi baru
        $this->assertEquals($newEmail, $user->email);

        // Password hash tidak berubah sama sekali
        $this->assertEquals($initialHash, $user->password);
        $this->assertTrue(Hash::check($rawPassword, $user->password));

        // Role dan atribut lain tetap utuh
        $this->assertEquals(User::ROLE_STORE_ADMIN, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_mitra_change_email_rejected_when_new_email_already_taken_by_store_admin(): void
    {
        $oldEmail = 'mitra_satu@example.com';
        $takenEmail = 'mitra_dua@example.com';

        $user1 = User::create([
            'name' => 'Mitra Satu',
            'email' => $oldEmail,
            'phone' => '081111111111',
            'password' => Hash::make('Secret1!'),
            'role' => User::ROLE_STORE_ADMIN,
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Mitra Dua',
            'email' => $takenEmail,
            'phone' => '082222222222',
            'password' => Hash::make('Secret2!'),
            'role' => User::ROLE_STORE_ADMIN,
            'is_active' => true,
        ]);

        $this->artisan('mitra:change-email', ['old' => $oldEmail, 'new' => $takenEmail])
            ->expectsOutputToContain("Email baru '{$takenEmail}' sudah terdaftar untuk user dengan role 'store_admin'.")
            ->assertExitCode(1);

        $user1->refresh();
        $this->assertEquals($oldEmail, $user1->email);
    }

    public function test_mitra_change_email_rejected_when_user_not_found(): void
    {
        $this->artisan('mitra:change-email', [
            'old' => 'nonexistent_mitra@example.com',
            'new' => 'valid_target@example.com',
        ])
            ->expectsOutputToContain("User dengan email 'nonexistent_mitra@example.com' dan role 'store_admin' tidak ditemukan.")
            ->assertExitCode(1);
    }

    public function test_mitra_change_email_cancelled_when_confirmation_declined(): void
    {
        $oldEmail = 'mitra_batal@example.com';
        $newEmail = 'mitra_batal_baru@example.com';

        $user = User::create([
            'name' => 'Mitra Batal',
            'email' => $oldEmail,
            'phone' => '083333333333',
            'password' => Hash::make('Secret3!'),
            'role' => User::ROLE_STORE_ADMIN,
            'is_active' => true,
        ]);

        $this->artisan('mitra:change-email', ['old' => $oldEmail, 'new' => $newEmail])
            ->expectsConfirmation(
                "Apakah Anda yakin ingin memperbarui email user #{$user->id} (Mitra Batal) dari '{$oldEmail}' menjadi '{$newEmail}'?",
                'no'
            )
            ->expectsOutputToContain('Operasi dibatalkan.')
            ->assertExitCode(0);

        $user->refresh();
        $this->assertEquals($oldEmail, $user->email);
    }
}
