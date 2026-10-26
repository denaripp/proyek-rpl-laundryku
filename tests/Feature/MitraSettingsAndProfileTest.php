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
}
