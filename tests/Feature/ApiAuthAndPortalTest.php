<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class ApiAuthAndPortalTest extends TestCase
{
    // ==========================================
    // 1. CUSTOMER AUTH & PORTAL TESTS
    // ==========================================

    public function test_customer_can_register_via_api(): void
    {
        $response = $this->postJson('/api/v1/auth/customer/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi_' . uniqid() . '@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '081234567890',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'user' => ['id', 'name', 'email', 'phone', 'role'],
            ]);

        $this->assertEquals(User::ROLE_CUSTOMER, $response->json('user.role'));
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_customer_can_login_via_api(): void
    {
        $email = 'cust_' . uniqid() . '@example.com';
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/customer/login', [
            'email' => $email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'user' => ['id', 'name', 'email', 'role'],
            ]);
    }

    public function test_mitra_credentials_fail_at_customer_login(): void
    {
        $email = 'mitra_' . uniqid() . '@example.com';
        User::factory()->create([
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_STORE_ADMIN,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/customer/login', [
            'email' => $email,
            'password' => 'secret123',
        ]);

        // Generic error to prevent enumeration
        $response->assertStatus(422)
            ->assertJson(['message' => __('auth.failed')]);
    }

    public function test_customer_can_access_own_profile_and_orders(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);
        $token = $customer->createToken('customer_token', ['role:customer'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/customer/profile');

        $response->assertStatus(200)
            ->assertJsonPath('user.id', $customer->id);
    }

    // ==========================================
    // 2. MITRA AUTH, 2FA & PORTAL TESTS
    // ==========================================

    public function test_mitra_can_register_and_creates_pending_store(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
        ]);
        \Laravel\Sanctum\Sanctum::actingAs($customer, ['role:customer']);

        $email = 'mitra_reg_' . uniqid() . '@example.com';
        $response = $this->postJson('/api/v1/auth/mitra/register', [
            'name' => 'Pak Joko',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '081298765432',
            'store_name' => 'Laundry Berkah ' . rand(100, 999),
            'store_address' => 'Jl. Sudirman No. 12',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'latitude' => -6.2100,
            'longitude' => 106.8200,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'user' => ['id', 'name', 'email', 'role'],
                'store' => ['id', 'name', 'status', 'is_active'],
            ]);

        $this->assertEquals(User::ROLE_STORE_ADMIN, $response->json('user.role'));
        $this->assertEquals('submitted', $response->json('store.status'));
        $this->assertFalse($response->json('store.is_active'));
    }

    public function test_mitra_with_pending_store_can_view_profile_but_cannot_access_orders(): void
    {
        $mitra = User::factory()->create([
            'role' => User::ROLE_STORE_ADMIN,
            'is_active' => true,
        ]);
        $store = Store::create([
            'owner_user_id' => $mitra->id,
            'name' => 'Toko Pending ' . rand(100, 999),
            'slug' => 'toko-pending-' . uniqid(),
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40111',
            'latitude' => -6.9175,
            'longitude' => 107.6191,
            'status' => 'submitted',
            'is_active' => false,
        ]);

        $token = $mitra->createToken('mitra_token', ['role:store_admin'])->plainTextToken;

        // 1. Can access store profile
        $profileRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/mitra/store');

        $profileRes->assertStatus(200)
            ->assertJsonPath('store.id', $store->id)
            ->assertJsonPath('store.is_approved', false);

        // 2. Cannot access orders
        $ordersRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/mitra/orders');

        $ordersRes->assertStatus(403)
            ->assertJson(['message' => 'Toko Anda masih berstatus pending dan belum dapat mengakses daftar pesanan.']);
    }

    public function test_mitra_store_staff_can_access_store_profile(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_STORE_ADMIN]);
        $store = Store::create([
            'owner_user_id' => $owner->id,
            'name' => 'Toko Owner ' . rand(100, 999),
            'slug' => 'toko-owner-' . uniqid(),
            'phone' => '081234567890',
            'address' => 'Jl. Veteran',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'postal_code' => '60175',
            'latitude' => -7.2575,
            'longitude' => 112.7521,
            'status' => 'approved',
            'is_active' => true,
        ]);

        // Staff user
        $staffUser = User::factory()->create([
            'role' => User::ROLE_STORE_ADMIN,
            'is_active' => true,
        ]);

        StoreStaff::create([
            'store_id' => $store->id,
            'user_id' => $staffUser->id,
            'role' => 'operator',
            'is_active' => true,
        ]);

        $token = $staffUser->createToken('mitra_token', ['role:store_admin'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/mitra/store');

        $response->assertStatus(200)
            ->assertJsonPath('store.id', $store->id)
            ->assertJsonPath('membership.is_staff', true);
    }

    public function test_mitra_with_2fa_requires_provisional_token_and_verification(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $email = 'mitra_2fa_' . uniqid() . '@example.com';
        $mitra = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_STORE_ADMIN,
            'google2fa_secret' => $secret,
            'is_active' => true,
        ]);

        // Step 1: Login
        $loginRes = $this->postJson('/api/v1/auth/mitra/login', [
            'email' => $email,
            'password' => 'secret123',
        ]);

        $loginRes->assertStatus(200)
            ->assertJson(['requires_2fa' => true])
            ->assertJsonStructure(['temp_token']);

        $tempToken = $loginRes->json('temp_token');

        // Provisional token CANNOT access full mitra portal
        $this->app['auth']->forgetGuards();
        $forbiddenRes = $this->withHeader('Authorization', 'Bearer ' . $tempToken)
            ->getJson('/api/v1/mitra/store');
        $forbiddenRes->assertStatus(403);

        // Step 2: Verify 2FA
        $this->app['auth']->forgetGuards();
        $verifyRes = $this->withHeader('Authorization', 'Bearer ' . $tempToken)
            ->postJson('/api/v1/auth/mitra/2fa', [
                'code' => '123456', // testing bypass
            ]);

        $verifyRes->assertStatus(200)
            ->assertJsonStructure(['token', 'token_type']);

        $fullToken = $verifyRes->json('token');

        // Full token CAN access mitra portal
        // Attach store first
        Store::create([
            'owner_user_id' => $mitra->id,
            'name' => 'Toko 2FA',
            'slug' => 'toko-2fa-' . uniqid(),
            'phone' => '081234567890',
            'address' => 'Jl. 2FA',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'postal_code' => '10110',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->app['auth']->forgetGuards();
        $accessRes = $this->withHeader('Authorization', 'Bearer ' . $fullToken)
            ->getJson('/api/v1/mitra/store');
        $accessRes->assertStatus(200);
    }

    // ==========================================
    // 3. COURIER AUTH & PORTAL TESTS
    // ==========================================

    public function test_courier_can_login_and_access_tasks(): void
    {
        $email = 'courier_' . uniqid() . '@example.com';
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_COURIER,
            'is_active' => true,
        ]);

        Courier::create([
            'user_id' => $user->id,
            'vehicle_type' => 'motorcycle',
            'vehicle_plate' => 'B 1234 CD',
            'is_available' => true,
        ]);

        $loginRes = $this->postJson('/api/v1/auth/kurir/login', [
            'email' => $email,
            'password' => 'secret123',
        ]);

        $loginRes->assertStatus(200);
        $token = $loginRes->json('token');

        $tasksRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/kurir/tasks');

        $tasksRes->assertStatus(200)
            ->assertJsonStructure(['courier' => ['id', 'is_available'], 'tasks']);
    }

    // ==========================================
    // 4. ADMIN AUTH (MANDATORY 2FA) & PORTAL TESTS
    // ==========================================

    public function test_admin_login_requires_two_steps_2fa(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $email = 'admin_' . uniqid() . '@example.com';
        $admin = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('adminsecret'),
            'role' => User::ROLE_SUPER_ADMIN,
            'google2fa_secret' => $secret,
            'is_active' => true,
        ]);

        // Step 1: Login
        $loginRes = $this->postJson('/api/v1/auth/admin/login', [
            'email' => $email,
            'password' => 'adminsecret',
        ]);

        $loginRes->assertStatus(200)
            ->assertJson(['requires_2fa' => true])
            ->assertJsonStructure(['temp_token']);

        $tempToken = $loginRes->json('temp_token');

        // Cannot access admin overview with temp token
        $this->app['auth']->forgetGuards();
        $blockedRes = $this->withHeader('Authorization', 'Bearer ' . $tempToken)
            ->getJson('/api/v1/admin/overview');
        $blockedRes->assertStatus(403);

        // Step 2: 2FA Verification
        $this->app['auth']->forgetGuards();
        $verifyRes = $this->withHeader('Authorization', 'Bearer ' . $tempToken)
            ->postJson('/api/v1/auth/admin/2fa', [
                'code' => '123456',
            ]);

        $verifyRes->assertStatus(200)
            ->assertJsonStructure(['token', 'token_type']);

        $fullToken = $verifyRes->json('token');

        // Now can access admin overview
        $this->app['auth']->forgetGuards();
        $overviewRes = $this->withHeader('Authorization', 'Bearer ' . $fullToken)
            ->getJson('/api/v1/admin/overview');

        $overviewRes->assertStatus(200)
            ->assertJsonStructure(['metrics']);
    }

    // ==========================================
    // 5. SECURITY & CROSS-ROLE ISOLATION TESTS
    // ==========================================

    public function test_cross_role_access_is_strictly_forbidden_403(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);
        $customerToken = $customer->createToken('cust_token', ['role:customer'])->plainTextToken;

        // Customer token attempting to access Mitra portal -> 403
        $this->withHeader('Authorization', 'Bearer ' . $customerToken)
            ->getJson('/api/v1/mitra/store')
            ->assertStatus(403);

        // Customer token attempting to access Courier portal -> 403
        $this->withHeader('Authorization', 'Bearer ' . $customerToken)
            ->getJson('/api/v1/kurir/tasks')
            ->assertStatus(403);

        // Customer token attempting to access Admin portal -> 403
        $this->withHeader('Authorization', 'Bearer ' . $customerToken)
            ->getJson('/api/v1/admin/overview')
            ->assertStatus(403);
    }

    public function test_unauthenticated_request_returns_401_json(): void
    {
        $response = $this->getJson('/api/v1/customer/profile');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    /**
     * Special Test (Instruction 3):
     * Token valid dengan ability benar tapi users.role sudah diubah di database harus ditolak (403).
     */
    public function test_valid_token_rejected_if_database_role_is_tampered_or_changed(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        // Token issued with ability 'role:customer'
        $token = $user->createToken('cust_token', ['role:customer'])->plainTextToken;

        // Verify it works initially
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/customer/profile')
            ->assertStatus(200);

        // Simulate database role change/tampering to 'store_admin'
        $user->role = User::ROLE_STORE_ADMIN;
        $user->save();

        // The token still has 'role:customer' ability, but users.role is now store_admin.
        // EnsureUserRole middleware must catch this and return 403!
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/customer/profile')
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden. Akun Anda tidak memiliki hak akses role yang sesuai.']);
    }

    public function test_old_tokens_are_revoked_on_new_login(): void
    {
        $email = 'relogin_' . uniqid() . '@example.com';
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        // First login
        $firstRes = $this->postJson('/api/v1/auth/customer/login', [
            'email' => $email,
            'password' => 'secret123',
        ]);
        $firstToken = $firstRes->json('token');

        // First token works
        $this->withHeader('Authorization', 'Bearer ' . $firstToken)
            ->getJson('/api/v1/customer/profile')
            ->assertStatus(200);

        // Second login (re-login)
        $secondRes = $this->postJson('/api/v1/auth/customer/login', [
            'email' => $email,
            'password' => 'secret123',
        ]);
        $secondToken = $secondRes->json('token');

        // First token is now revoked -> 401
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $firstToken)
            ->getJson('/api/v1/customer/profile')
            ->assertStatus(401);

        // Second token works -> 200
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $secondToken)
            ->getJson('/api/v1/customer/profile')
            ->assertStatus(200);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);
        $token = $user->createToken('customer_token', ['role:customer'])->plainTextToken;

        $logoutRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/customer/logout');

        $logoutRes->assertStatus(200);

        // Token is revoked -> 401
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/customer/profile')
            ->assertStatus(401);
    }
}
