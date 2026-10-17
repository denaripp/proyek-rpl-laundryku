<?php

namespace Tests\Feature;

use App\Models\CourierUser;
use App\Models\CustomerUser;
use App\Models\MitraUser;
use App\Models\AdminUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DefaultUsersSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleIsolatedLoginBugTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SuperAdminSeeder::class);
        $this->seed(DefaultUsersSeeder::class);
    }

    /**
     * 1. Login berhasil di guard yang benar dengan akun role yang benar
     */
    public function test_customer_can_login_via_web_door(): void
    {
        $response = $this->post(route('login'), [
            'email' => 'customer@laundryku.test',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('customer.dashboard'));
        $this->assertTrue(Auth::guard('web')->check());
        $this->assertFalse(Auth::guard('mitra')->check());
        $this->assertFalse(Auth::guard('kurir')->check());
        $this->assertFalse(Auth::guard('admin')->check());
    }

    public function test_mitra_can_login_via_mitra_door(): void
    {
        $response = $this->post(route('mitra.login'), [
            'email' => 'mitra@laundryku.test',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('mitra.dashboard'));
        $this->assertTrue(Auth::guard('mitra')->check());
        $this->assertFalse(Auth::guard('web')->check());
        $this->assertFalse(Auth::guard('kurir')->check());
        $this->assertFalse(Auth::guard('admin')->check());
    }

    public function test_courier_can_login_via_kurir_door(): void
    {
        $response = $this->post(route('kurir.login'), [
            'email' => 'kurir@laundryku.test',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('kurir.dashboard'));
        $this->assertTrue(Auth::guard('kurir')->check());
        $this->assertFalse(Auth::guard('web')->check());
        $this->assertFalse(Auth::guard('mitra')->check());
        $this->assertFalse(Auth::guard('admin')->check());
    }

    public function test_admin_can_login_via_admin_door_and_requires_2fa(): void
    {
        $response = $this->post(route('admin.login'), [
            'email' => 'admin@laundryku.test',
            'password' => 'admin12345',
        ]);

        $response->assertRedirect(route('admin.2fa'));
        $response->assertSessionHas('admin_auth_user_id');
    }

    /**
     * 2. Kredensial customer di /mitra/login, /kurir/login, /admin/login: tetap gagal
     */
    public function test_customer_credentials_fail_at_mitra_door(): void
    {
        $response = $this->post(route('mitra.login'), [
            'email' => 'customer@laundryku.test',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email' => 'Kontak atau password salah.']);
        $this->assertFalse(Auth::guard('mitra')->check());
    }

    public function test_customer_credentials_fail_at_kurir_door(): void
    {
        $response = $this->post(route('kurir.login'), [
            'email' => 'customer@laundryku.test',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email' => 'Kontak atau password salah.']);
        $this->assertFalse(Auth::guard('kurir')->check());
    }

    public function test_customer_credentials_fail_at_admin_door(): void
    {
        $response = $this->post(route('admin.login'), [
            'email' => 'customer@laundryku.test',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email' => 'Kontak atau password salah.']);
        $this->assertFalse(Auth::guard('admin')->check());
    }

    /**
     * 3. Password salah: tetap menampilkan pesan error yang sama ("Kontak atau password salah.")
     */
    public function test_wrong_password_shows_consistent_error_message(): void
    {
        // Customer door
        $customerRes = $this->post(route('login'), [
            'email' => 'customer@laundryku.test',
            'password' => 'wrongpassword123',
        ]);
        $customerRes->assertSessionHasErrors(['email' => 'Kontak atau password salah.']);

        // Mitra door
        $mitraRes = $this->post(route('mitra.login'), [
            'email' => 'mitra@laundryku.test',
            'password' => 'wrongpassword123',
        ]);
        $mitraRes->assertSessionHasErrors(['email' => 'Kontak atau password salah.']);

        // Kurir door
        $kurirRes = $this->post(route('kurir.login'), [
            'email' => 'kurir@laundryku.test',
            'password' => 'wrongpassword123',
        ]);
        $kurirRes->assertSessionHasErrors(['email' => 'Kontak atau password salah.']);

        // Admin door
        $adminRes = $this->post(route('admin.login'), [
            'email' => 'admin@laundryku.test',
            'password' => 'wrongpassword123',
        ]);
        $adminRes->assertSessionHasErrors(['email' => 'Kontak atau password salah.']);
    }

    /**
     * 4. Legacy sync: user with role='customer' in DB cannot login to /mitra/login until synced
     */
    public function test_legacy_account_role_sync_allows_proper_guard_login(): void
    {
        $legacyStoreUser = User::create([
            'name' => 'Legacy Store Owner',
            'email' => 'legacy.store@laundryku.test',
            'password' => Hash::make('password123'),
            'role' => 'customer', // old/wrong role in DB
            'is_active' => true,
        ]);

        $storeOwnerRole = Role::where('name', 'store_owner')->first();
        $legacyStoreUser->roles()->attach($storeOwnerRole->id);

        // Before sync: fails at mitra door because role is customer
        $beforeRes = $this->post(route('mitra.login'), [
            'email' => 'legacy.store@laundryku.test',
            'password' => 'password123',
        ]);
        $beforeRes->assertSessionHasErrors(['email' => 'Kontak atau password salah.']);

        // Run seeder sync
        $this->seed(DefaultUsersSeeder::class);

        // After sync: role column is updated to store_admin, login succeeds!
        $this->assertEquals(User::ROLE_STORE_ADMIN, $legacyStoreUser->fresh()->role);

        $afterRes = $this->post(route('mitra.login'), [
            'email' => 'legacy.store@laundryku.test',
            'password' => 'password123',
        ]);
        $afterRes->assertRedirect(route('mitra.dashboard'));
        $this->assertTrue(Auth::guard('mitra')->check());
    }
}
