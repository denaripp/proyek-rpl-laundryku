<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MitraLoginReproductionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SuperAdminSeeder::class);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /**
     * Tes Reproduksi Lengkap:
     * 1. Customer terautentikasi mendaftar mitra (variasi huruf besar dan spasi).
     * 2. Login sebelum dan sesudah admin menyetujui toko.
     * 3. Respon login harus memuat user dan store untuk redirect portal yang tepat.
     * 4. Login dengan password salah tetap ditolak 422 ("Kontak atau password salah.").
     * 5. Isolasi peran jika ada akun customer dengan email/kontak serupa.
     */
    public function test_mitra_registration_login_variations_and_approval_flow(): void
    {
        // 1. Customer terautentikasi awal yang mendaftar mitra
        $applicantCustomer = User::factory()->create([
            'email' => 'customer_applicant@example.com',
            'phone' => '081234567800',
            'role' => User::ROLE_CUSTOMER,
        ]);
        Sanctum::actingAs($applicantCustomer, ['role:customer']);

        $mitraEmail = 'bhevinofficialstore@gmail.com';
        $mitraPassword = 'Password123!';

        // 2. Mendaftar mitra dengan variasi huruf besar dan spasi pada email
        $regResponse = $this->postJson('/api/v1/auth/mitra/register', [
            'name' => 'Bhevin Store Owner',
            'email' => '   BhevinOfficialStore@Gmail.Com   ',
            'phone' => '+62 898-7654-3210',
            'password' => $mitraPassword,
            'password_confirmation' => $mitraPassword,
            'store_name' => 'Bhevin Official Laundry',
            'store_address' => 'Jl. Dipati Ukur No. 99, Bandung',
            'latitude' => -6.914744,
            'longitude' => 107.609810,
        ]);

        $regResponse->assertStatus(201);
        $storeId = $regResponse->json('store.id');
        $this->assertNotNull($storeId);
        $this->assertEquals('submitted', $regResponse->json('store.status'));

        // Reset auth context
        $this->app['auth']->forgetGuards();

        // 3. Login SEBELUM toko disetujui admin
        // A. Email huruf kecil exact
        $loginBeforeExact = $this->postJson('/api/v1/auth/mitra/login', [
            'email' => $mitraEmail,
            'password' => $mitraPassword,
        ]);
        $loginBeforeExact->assertStatus(200)
            ->assertJsonPath('user.email', $mitraEmail);

        // B. Email dengan variasi huruf besar
        $loginBeforeUpper = $this->postJson('/api/v1/auth/mitra/login', [
            'email' => 'BhevinOfficialStore@Gmail.Com',
            'password' => $mitraPassword,
        ]);
        $loginBeforeUpper->assertStatus(200);

        // C. Email dengan spasi di awal dan akhir
        $loginBeforeSpace = $this->postJson('/api/v1/auth/mitra/login', [
            'email' => '   bhevinofficialstore@gmail.com   ',
            'password' => $mitraPassword,
        ]);
        $loginBeforeSpace->assertStatus(200);

        // 4. Admin menyetujui toko
        $admin = User::where('role', User::ROLE_SUPER_ADMIN)->first() ?? User::factory()->create([
            'email' => 'admin_reviewer@laundryku.test',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
        $adminRole = Role::where('name', 'platform_admin')->first();
        if ($adminRole && !$admin->roles()->where('roles.id', $adminRole->id)->exists()) {
            $admin->roles()->attach($adminRole->id, ['assigned_at' => now()]);
        }
        Sanctum::actingAs($admin, ['role:super_admin']);

        $approveRes = $this->postJson("/api/v1/admin/stores/{$storeId}/approve", [
            'note' => 'Disetujui untuk operasional resmi',
        ]);
        $approveRes->assertStatus(200);

        // Reset auth context
        $this->app['auth']->forgetGuards();

        // 5. Login SESUDAH toko disetujui admin
        // A. Email exact
        $loginAfterExact = $this->postJson('/api/v1/auth/mitra/login', [
            'email' => $mitraEmail,
            'password' => $mitraPassword,
        ]);
        $loginAfterExact->assertStatus(200)
            ->assertJsonPath('user.email', $mitraEmail)
            ->assertJsonPath('store.status', 'approved');

        // B. Email variasi huruf besar & spasi
        $loginAfterMixed = $this->postJson('/api/v1/auth/mitra/login', [
            'email' => '   BhevinOfficialStore@Gmail.Com   ',
            'password' => $mitraPassword,
        ]);
        $loginAfterMixed->assertStatus(200)
            ->assertJsonPath('store.status', 'approved');

        // 6. Tes Password Salah Tetap Ditolak
        $wrongPasswordRes = $this->postJson('/api/v1/auth/mitra/login', [
            'email' => $mitraEmail,
            'password' => 'WrongPassword999!',
        ]);
        $wrongPasswordRes->assertStatus(422)
            ->assertJson([
                'message' => 'Kontak atau password salah.',
            ]);
    }

    /**
     * Tes: Jika ada user customer dengan email yang sama di tabel users (composite unique email, role),
     * query login mitra harus secara spesifik memilih user bertipe store_admin,
     * bukan mengambil user customer yang menyebabkan "Kontak atau password salah."
     */
    public function test_mitra_login_does_not_collide_with_customer_account(): void
    {
        $sharedEmail = 'dual_role_user@example.com';

        // 1. Akun Customer dibuat terlebih dahulu (ID lebih kecil)
        $customer = User::create([
            'name' => 'Dual Customer',
            'email' => $sharedEmail,
            'phone' => '081234567888',
            'password' => Hash::make('CustomerPass123!'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        // 2. Akun Mitra dibuat setelahnya dengan email yang sama (karena composite unique email, role)
        $mitra = User::create([
            'name' => 'Dual Mitra',
            'email' => $sharedEmail,
            'phone' => '081234567889',
            'password' => Hash::make('MitraPass123!'),
            'role' => User::ROLE_STORE_ADMIN,
            'is_active' => true,
        ]);

        $store = Store::create([
            'owner_user_id' => $mitra->id,
            'name' => 'Dual Store',
            'slug' => 'dual-store-12345',
            'phone' => '081234567889',
            'address' => 'Jl. Sukajadi No. 12',
            'city' => 'Kota Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40162',
            'latitude' => -6.890000,
            'longitude' => 107.600000,
            'status' => 'approved',
            'is_active' => true,
        ]);

        // 3. Login mitra dengan email sharedEmail dan password mitra
        $loginRes = $this->postJson('/api/v1/auth/mitra/login', [
            'email' => $sharedEmail,
            'password' => 'MitraPass123!',
        ]);

        $loginRes->assertStatus(200)
            ->assertJsonPath('user.id', $mitra->id)
            ->assertJsonPath('user.role', User::ROLE_STORE_ADMIN);
    }
}
