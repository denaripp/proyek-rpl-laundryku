<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\StoreStaff;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MitraSettingsAndProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SuperAdminSeeder::class);
    }

    protected function createStoreAdminUser(string $email = 'owner@laundry.id', string $password = 'password123'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'role' => User::ROLE_STORE_ADMIN,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $role = Role::where('name', 'store_admin')->first();
        if ($role) {
            $user->roles()->attach($role->id, ['assigned_at' => now()]);
        }

        return $user;
    }

    public function test_store_owner_can_update_store_profile_with_whitelisted_fields(): void
    {
        $owner = $this->createStoreAdminUser('owner1@laundry.id');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Toko Lama',
            'slug' => 'toko-lama',
            'description' => 'Deskripsi lama',
            'phone' => '08111111111',
            'address' => 'Alamat lama',
            'status' => 'approved',
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner, ['role:store_admin']);

        $payload = [
            'name' => 'Toko Baru Bersih',
            'description' => 'Deskripsi gerai yang telah diperbarui',
            'phone' => '08222222222',
            'address' => 'Jl. Kebersihan No. 45',
            // Field terlarang yang dicoba disisipkan
            'slug' => 'hacked-slug',
            'status' => 'draft',
            'is_active' => false,
            'owner_user_id' => 999,
        ];

        $response = $this->patchJson('/api/v1/mitra/store', $payload);
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.store.name', 'Toko Baru Bersih');
        $response->assertJsonPath('data.store.phone', '08222222222');

        $store->refresh();

        // Field yang sah harus terupdate
        $this->assertEquals('Toko Baru Bersih', $store->name);
        $this->assertEquals('Deskripsi gerai yang telah diperbarui', $store->description);
        $this->assertEquals('08222222222', $store->phone);
        $this->assertEquals('Jl. Kebersihan No. 45', $store->address);

        // Field terlarang TIDAK BOLEH berubah
        $this->assertEquals('toko-lama', $store->slug);
        $this->assertEquals('approved', $store->status);
        $this->assertTrue((bool) $store->is_active);
        $this->assertEquals($owner->id, $store->owner_user_id);
    }

    public function test_store_staff_cannot_update_store_profile_and_gets_403(): void
    {
        $owner = $this->createStoreAdminUser('owner2@laundry.id');
        $staffUser = $this->createStoreAdminUser('staff@laundry.id');

        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Toko Bos',
            'phone' => '08123456789',
            'address' => 'Alamat Bos',
        ]);

        StoreStaff::create([
            'store_id' => $store->id,
            'user_id' => $staffUser->id,
            'role' => 'operator',
            'is_active' => true,
        ]);

        Sanctum::actingAs($staffUser, ['role:store_admin']);

        $response = $this->patchJson('/api/v1/mitra/store', [
            'name' => 'Toko Diubah Staf',
            'phone' => '08999999999',
            'address' => 'Alamat Staf',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('Toko Bos', $store->fresh()->name);
    }

    public function test_customer_cannot_access_mitra_store_update_endpoint(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        Sanctum::actingAs($customer, ['role:customer']);

        $response = $this->patchJson('/api/v1/mitra/store', [
            'name' => 'Coba Ubah',
            'phone' => '08111111111',
            'address' => 'Alamat',
        ]);

        $response->assertStatus(403);
    }

    public function test_mitra_password_change_validates_current_password_and_confirmation(): void
    {
        $owner = $this->createStoreAdminUser('owner_pwd@laundry.id', 'OldSecret123');

        Sanctum::actingAs($owner, ['role:store_admin']);

        // 1. Current password salah -> 422
        $resWrongCurrent = $this->putJson('/api/v1/mitra/password', [
            'current_password' => 'WrongSecret123',
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ]);
        $resWrongCurrent->assertStatus(422);

        // 2. Password baru kurang dari 8 karakter -> 422
        $resShort = $this->putJson('/api/v1/mitra/password', [
            'current_password' => 'OldSecret123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);
        $resShort->assertStatus(422);

        // 3. Konfirmasi password tidak cocok -> 422
        $resMismatch = $this->putJson('/api/v1/mitra/password', [
            'current_password' => 'OldSecret123',
            'password' => 'NewSecret123',
            'password_confirmation' => 'DifferentSecret123',
        ]);
        $resMismatch->assertStatus(422);

        // Password asli di DB tidak boleh berubah
        $this->assertTrue(Hash::check('OldSecret123', $owner->fresh()->password));
    }

    public function test_mitra_password_change_succeeds_revokes_other_tokens_and_enables_new_login(): void
    {
        $owner = $this->createStoreAdminUser('owner_auth@laundry.id', 'OriginalPassword123');

        // Buat 3 token untuk user ini
        $currentToken = $owner->createToken('session_web', ['role:store_admin']);
        $otherToken1 = $owner->createToken('mobile_app_1', ['role:store_admin']);
        $otherToken2 = $owner->createToken('mobile_app_2', ['role:store_admin']);

        $this->assertEquals(3, $owner->tokens()->count());

        // Otentikasi dengan currentToken
        Sanctum::actingAs($owner, ['role:store_admin']);
        $owner->withAccessToken($currentToken->accessToken);

        $response = $this->putJson('/api/v1/mitra/password', [
            'current_password' => 'OriginalPassword123',
            'password' => 'BrandNewPassword456',
            'password_confirmation' => 'BrandNewPassword456',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Pastikan token lain dicabut dan token saat ini tetap ada
        $remainingTokens = $owner->tokens()->pluck('id')->toArray();
        $this->assertContains($currentToken->accessToken->id, $remainingTokens);
        $this->assertNotContains($otherToken1->accessToken->id, $remainingTokens);
        $this->assertNotContains($otherToken2->accessToken->id, $remainingTokens);

        // Verifikasi login: password lama gagal, password baru berhasil
        $owner->refresh();
        $this->assertFalse(Hash::check('OriginalPassword123', $owner->password));
        $this->assertTrue(Hash::check('BrandNewPassword456', $owner->password));

        // Test login mitra API dengan password lama
        $failLogin = $this->postJson('/api/v1/auth/mitra/login', [
            'email' => 'owner_auth@laundry.id',
            'password' => 'OriginalPassword123',
        ]);
        $failLogin->assertStatus(422);

        // Test login mitra API dengan password baru
        $successLogin = $this->postJson('/api/v1/auth/mitra/login', [
            'email' => 'owner_auth@laundry.id',
            'password' => 'BrandNewPassword456',
        ]);
        $successLogin->assertStatus(200);
        $this->assertNotEmpty($successLogin->json('token'));
    }

    public function test_patch_profile_allows_store_admin_to_update_name_and_phone_only(): void
    {
        $owner = $this->createStoreAdminUser('owner_profile@laundry.id');

        Sanctum::actingAs($owner, ['role:store_admin']);

        $response = $this->patchJson('/api/v1/profile', [
            'name' => 'Nama Pemilik Baru',
            'phone' => '08777777777',
            'email' => 'hacked_email@other.id',
            'role' => 'super_admin',
        ]);

        $response->assertStatus(200);

        $owner->refresh();
        // Nama dan phone terupdate
        $this->assertEquals('Nama Pemilik Baru', $owner->name);
        $this->assertEquals('08777777777', $owner->phone);

        // Email dan role TIDAK berubah
        $this->assertEquals('owner_profile@laundry.id', $owner->email);
        $this->assertEquals(User::ROLE_STORE_ADMIN, $owner->role);
    }

    public function test_store_owner_can_update_operational_hours_and_service_radius(): void
    {
        $owner = $this->createStoreAdminUser('owner_settings@laundry.id');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Toko Operasional',
            'status' => 'approved',
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner, ['role:store_admin']);

        $operationalHours = [
            'senin' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
            'selasa' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
            'rabu' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
            'kamis' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
            'jumat' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
            'sabtu' => ['open' => '09:00', 'close' => '18:00', 'is_closed' => false],
            'minggu' => ['open' => '09:00', 'close' => '17:00', 'is_closed' => true],
        ];

        $payload = [
            'name' => 'Toko Operasional Baru',
            'phone' => '081299998888',
            'address' => 'Jl. Riau No. 88, Citarum',
            'city' => 'Kota Bandung',
            'province' => 'Jawa Barat',
            'latitude' => -6.908500,
            'longitude' => 107.619800,
            'service_radius_km' => 7.50,
            'operational_hours' => $operationalHours,
        ];

        $response = $this->patchJson('/api/v1/mitra/store', $payload);
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.store.service_radius_km', 7.5);
        $response->assertJsonPath('data.store.operational_hours.senin.open', '08:00');
        $response->assertJsonPath('data.store.operational_hours.minggu.is_closed', true);
        $response->assertJsonPath('data.store.city', 'Kota Bandung');
        $response->assertJsonPath('data.store.province', 'Jawa Barat');

        $store->refresh();
        $this->assertEquals(7.50, (float) $store->service_radius_km);
        $this->assertEquals('Kota Bandung', $store->city);
        $this->assertEquals('Jawa Barat', $store->province);
        $this->assertEquals(-6.908500, (float) $store->latitude);
        $this->assertEquals(107.619800, (float) $store->longitude);
        $this->assertEquals('08:00', $store->operational_hours['senin']['open']);
        $this->assertTrue((bool) $store->operational_hours['minggu']['is_closed']);

        // Verify GET /api/v1/mitra/store returns the new fields
        $getRes = $this->getJson('/api/v1/mitra/store');
        $getRes->assertStatus(200);
        $getRes->assertJsonPath('data.store.service_radius_km', 7.5);
        $getRes->assertJsonPath('data.store.city', 'Kota Bandung');
        $getRes->assertJsonPath('data.store.province', 'Jawa Barat');
        $getRes->assertJsonPath('data.store.operational_hours.senin.open', '08:00');
    }

    public function test_update_store_rejects_invalid_operational_hours(): void
    {
        $owner = $this->createStoreAdminUser('owner_invalid_hours@laundry.id');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Toko Validasi Jam',
        ]);

        Sanctum::actingAs($owner, ['role:store_admin']);

        // 1. Jam tutup <= jam buka (open 18:00, close 08:00)
        $invalidClose = [
            'name' => 'Toko Validasi Jam',
            'phone' => '081234567890',
            'address' => 'Jl. Alamat',
            'operational_hours' => [
                'senin' => ['open' => '18:00', 'close' => '08:00', 'is_closed' => false],
                'selasa' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
                'rabu' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
                'kamis' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
                'jumat' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
                'sabtu' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
                'minggu' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
            ],
        ];
        $res1 = $this->patchJson('/api/v1/mitra/store', $invalidClose);
        $res1->assertStatus(422);
        $res1->assertJsonValidationErrors(['operational_hours.senin.close']);

        // 2. Format jam salah (bukan HH:MM, misal '8:00' atau '25:00')
        $invalidFormat = [
            'name' => 'Toko Validasi Jam',
            'phone' => '081234567890',
            'address' => 'Jl. Alamat',
            'operational_hours' => [
                'senin' => ['open' => '8:00', 'close' => '25:00', 'is_closed' => false],
                'selasa' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
                'rabu' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
                'kamis' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
                'jumat' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
                'sabtu' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
                'minggu' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
            ],
        ];
        $res2 = $this->patchJson('/api/v1/mitra/store', $invalidFormat);
        $res2->assertStatus(422);
        $res2->assertJsonValidationErrors(['operational_hours.senin.open', 'operational_hours.senin.close']);

        // 3. Hari tidak lengkap (kurang 'minggu')
        $missingDay = [
            'name' => 'Toko Validasi Jam',
            'phone' => '081234567890',
            'address' => 'Jl. Alamat',
            'operational_hours' => [
                'senin' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
                'selasa' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
                'rabu' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
                'kamis' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
                'jumat' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
                'sabtu' => ['open' => '08:00', 'close' => '20:00', 'is_closed' => false],
            ],
        ];
        $res3 = $this->patchJson('/api/v1/mitra/store', $missingDay);
        $res3->assertStatus(422);
        $res3->assertJsonValidationErrors(['operational_hours.minggu']);
    }

    public function test_update_store_rejects_invalid_service_radius(): void
    {
        $owner = $this->createStoreAdminUser('owner_radius@laundry.id');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Toko Validasi Radius',
        ]);

        Sanctum::actingAs($owner, ['role:store_admin']);

        // Radius < 0.5 km
        $resTooSmall = $this->patchJson('/api/v1/mitra/store', [
            'name' => 'Toko Validasi Radius',
            'phone' => '081234567890',
            'address' => 'Jl. Alamat',
            'service_radius_km' => 0.2,
        ]);
        $resTooSmall->assertStatus(422);
        $resTooSmall->assertJsonValidationErrors(['service_radius_km']);

        // Radius > 50 km
        $resTooBig = $this->patchJson('/api/v1/mitra/store', [
            'name' => 'Toko Validasi Radius',
            'phone' => '081234567890',
            'address' => 'Jl. Alamat',
            'service_radius_km' => 55.0,
        ]);
        $resTooBig->assertStatus(422);
        $resTooBig->assertJsonValidationErrors(['service_radius_km']);

        // Radius dengan desimal lebih dari 2 angka
        $resTooManyDecimals = $this->patchJson('/api/v1/mitra/store', [
            'name' => 'Toko Validasi Radius',
            'phone' => '081234567890',
            'address' => 'Jl. Alamat',
            'service_radius_km' => 5.125,
        ]);
        $resTooManyDecimals->assertStatus(422);
        $resTooManyDecimals->assertJsonValidationErrors(['service_radius_km']);
    }

    public function test_other_store_owner_cannot_update_store_profile_and_gets_403(): void
    {
        $ownerA = $this->createStoreAdminUser('ownerA@laundry.id');
        $storeA = Store::factory()->create([
            'owner_user_id' => $ownerA->id,
            'name' => 'Toko Milik A',
            'phone' => '081111111111',
            'address' => 'Alamat A',
        ]);

        $ownerB = $this->createStoreAdminUser('ownerB@laundry.id');
        $storeB = Store::factory()->create([
            'owner_user_id' => $ownerB->id,
            'name' => 'Toko Milik B',
            'phone' => '082222222222',
            'address' => 'Alamat B',
        ]);

        // Owner B acts as authenticated user
        Sanctum::actingAs($ownerB, ['role:store_admin']);

        // When Owner B calls /api/v1/mitra/store, it resolves Store B (their own store), NOT Store A
        $response = $this->patchJson('/api/v1/mitra/store', [
            'name' => 'Toko B Diubah',
            'phone' => '083333333333',
            'address' => 'Alamat B Baru',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Toko B Diubah', $storeB->fresh()->name);
        // Store A must remain completely unaffected
        $this->assertEquals('Toko Milik A', $storeA->fresh()->name);
    }

    public function test_registration_then_read_settings_keeps_exact_location_consistent(): void
    {
        // 1. Customer initiates registration
        $customer = User::factory()->create([
            'email' => 'customer_applicant@laundry.id',
            'phone' => '081234560001',
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        Sanctum::actingAs($customer, ['role:customer']);

        $registrationPayload = [
            'name' => 'Arif Laundry Owner',
            'email' => 'ariflaundry@laundry.id',
            'phone' => '081234560099',
            'password' => 'SecurePassword123',
            'password_confirmation' => 'SecurePassword123',
            'store_name' => 'Arif Laundry Baros',
            'store_address' => 'Jl. Baros No. 12, Kel. Baros, Kec. Cimahi Selatan',
            'city' => 'Kota Cimahi',
            'province' => 'Jawa Barat',
            'latitude' => -6.885600,
            'longitude' => 107.543200,
        ];

        $regResponse = $this->postJson('/api/v1/auth/mitra/register', $registrationPayload);
        $regResponse->assertStatus(201);
        $this->assertNotEmpty($regResponse->json('token'));

        // 2. Fetch the newly created mitra user and store
        $mitraUser = User::where('email', 'ariflaundry@laundry.id')->firstOrFail();
        $this->assertEquals(User::ROLE_STORE_ADMIN, $mitraUser->role);

        $newStore = Store::where('owner_user_id', $mitraUser->id)->firstOrFail();
        $this->assertEquals('Arif Laundry Baros', $newStore->name);
        $this->assertEquals('Jl. Baros No. 12, Kel. Baros, Kec. Cimahi Selatan', $newStore->address);
        $this->assertEquals('Kota Cimahi', $newStore->city);
        $this->assertEquals('Jawa Barat', $newStore->province);
        $this->assertEquals(-6.885600, (float) $newStore->latitude);
        $this->assertEquals(107.543200, (float) $newStore->longitude);

        // 3. Log in / Authenticate as the new mitra user
        Sanctum::actingAs($mitraUser, ['role:store_admin']);

        // 4. Read GET /api/v1/mitra/store (the endpoint queried by /store/settings)
        $settingsResponse = $this->getJson('/api/v1/mitra/store');
        $settingsResponse->assertStatus(200);
        $settingsResponse->assertJsonPath('success', true);

        $storeSettingsData = $settingsResponse->json('data.store');
        $this->assertEquals('Arif Laundry Baros', $storeSettingsData['name']);
        $this->assertEquals('Jl. Baros No. 12, Kel. Baros, Kec. Cimahi Selatan', $storeSettingsData['address']);
        $this->assertEquals('Kota Cimahi', $storeSettingsData['city']);
        $this->assertEquals('Jawa Barat', $storeSettingsData['province']);
        $this->assertEquals(-6.885600, (float) $storeSettingsData['latitude']);
        $this->assertEquals(107.543200, (float) $storeSettingsData['longitude']);
    }
}
