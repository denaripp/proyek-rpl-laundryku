<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\StoreStaff;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase2SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        $this->seed(SuperAdminSeeder::class);
    }

    public function test_provisional_2fa_token_cannot_access_protected_business_endpoints(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_STORE_ADMIN,
            'is_active' => true,
        ]);

        $provisionalToken = $user->createToken('provisional', ['2fa-pending'])->plainTextToken;

        // Attempting to access profile
        $response = $this->withHeader('Authorization', 'Bearer ' . $provisionalToken)
            ->getJson('/api/v1/profile');

        $response->assertStatus(403)
            ->assertJson(['requires_2fa' => true]);

        // Attempting to access store dashboard
        $responseStore = $this->withHeader('Authorization', 'Bearer ' . $provisionalToken)
            ->getJson('/api/v1/store/dashboard');

        $responseStore->assertStatus(403);
    }

    public function test_pending_2fa_web_session_cannot_access_2fa_disable(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'two_factor_enabled' => true,
            'google2fa_secret' => 'TESTSECRETKEY123',
            'is_active' => true,
            'password' => Hash::make('password123'),
        ]);

        $response = $this->actingAs($user, 'web')
            ->withSession(['pending_2fa' => true])
            ->post('/2fa/disable', [
                'password' => 'password123',
            ]);

        // Should be redirected to 2fa.verify, not allowed to disable
        $response->assertRedirect(route('2fa.verify'));
        $this->assertTrue((bool) $user->fresh()->two_factor_enabled);
    }

    public function test_otp_login_endpoint_rejects_non_customer_accounts(): void
    {
        $admin = User::where('role', User::ROLE_SUPER_ADMIN)->first();

        $response = $this->postJson('/api/v1/auth/login', [
            'channel' => 'email',
            'destination' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_PORTAL',
                ],
            ]);
    }

    public function test_otp_registration_cannot_reuse_or_overwrite_inactive_admin(): void
    {
        $inactiveAdmin = User::factory()->create([
            'name' => 'Inactive Super Admin',
            'email' => 'inactive.admin@example.com',
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => false,
            'email_verified_at' => now(),
            'password' => Hash::make('originalpassword'),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Attacker Takeover',
            'channel' => 'email',
            'destination' => 'inactive.admin@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_ALREADY_EXISTS',
                ],
            ]);

        $this->assertEquals('originalpassword', Hash::check('originalpassword', $inactiveAdmin->fresh()->password) ? 'originalpassword' : 'wrong');
    }

    public function test_suspended_user_cannot_use_existing_sanctum_token(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        $token = $user->createToken('test_token', ['role:customer'])->plainTextToken;

        // Valid access while active
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/profile')
            ->assertStatus(200);

        // Deactivate / suspend user
        $user->update(['is_active' => false]);
        $this->app['auth']->forgetGuards();

        // Subsequent access with existing token must fail with 401 unauthenticated
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/profile')
            ->assertStatus(401);
    }

    public function test_admin_suspending_user_revokes_all_sanctum_tokens(): void
    {
        $admin = User::where('role', User::ROLE_SUPER_ADMIN)->first();
        $adminToken = $admin->createToken('admin_token', ['role:super_admin'])->plainTextToken;

        $targetUser = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);
        $userToken = $targetUser->createToken('cust_token', ['role:customer'])->plainTextToken;

        // Admin suspends user
        $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->patchJson('/api/v1/admin/users/' . $targetUser->id . '/status', [
                'status' => 'suspended',
                'reason' => 'Violation of terms',
            ])
            ->assertStatus(200);

        $this->app['auth']->forgetGuards();

        // Target user token was deleted and cannot access profile
        $this->withHeader('Authorization', 'Bearer ' . $userToken)
            ->getJson('/api/v1/profile')
            ->assertStatus(401);
    }

    public function test_token_with_customer_role_cannot_access_super_admin_api_even_if_multi_role(): void
    {
        // User with super_admin role in DB
        $user = User::where('role', User::ROLE_SUPER_ADMIN)->first();

        // Token minted specifically with 'role:customer' (e.g. mobile customer session)
        $customerToken = $user->createToken('mobile_token', ['role:customer'])->plainTextToken;

        // Attempting to call admin endpoint
        $response = $this->withHeader('Authorization', 'Bearer ' . $customerToken)
            ->getJson('/api/v1/admin/overview');

        // Must be rejected by ability/role check (403)
        $response->assertStatus(403);
    }

    public function test_platform_admin_cannot_update_commission_or_ai_settings(): void
    {
        $platformAdminRole = Role::where('name', 'platform_admin')->first();
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
        if ($platformAdminRole) {
            $admin->roles()->attach($platformAdminRole->id, ['status' => 'active', 'assigned_at' => now()]);
        }

        $token = $admin->createToken('admin_token', ['role:super_admin'])->plainTextToken;

        // Attempt update commission (owner only)
        $responseComm = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/admin/platform-settings/commission', [
                'amount' => 15,
            ]);

        $responseComm->assertStatus(403);

        // Attempt update AI settings (owner only)
        $responseAi = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/admin/ai-settings', [
                'model' => 'openai/gpt-4o',
            ]);

        $responseAi->assertStatus(403);
    }

    public function test_revoked_store_staff_cannot_view_store_payments(): void
    {
        $storeOwner = User::factory()->create(['role' => User::ROLE_STORE_ADMIN, 'is_active' => true]);
        $store = Store::factory()->create(['owner_user_id' => $storeOwner->id, 'status' => 'approved', 'is_active' => true]);

        $staffUser = User::factory()->create(['role' => User::ROLE_STORE_ADMIN, 'is_active' => true]);
        $storeStaff = StoreStaff::create([
            'store_id' => $store->id,
            'user_id' => $staffUser->id,
            'role' => 'staff',
            'is_active' => false, // Revoked/inactive staff
        ]);

        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'is_active' => true]);
        $order = Order::factory()->create(['store_id' => $store->id, 'customer_id' => $customer->id]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50000,
            'status' => 'paid',
            'payment_method' => 'bank_transfer',
            'reference' => 'PAY-TEST-REVOKED-123',
        ]);

        $response = $this->actingAs($staffUser, 'web')
            ->getJson('/api/payments');

        $response->assertStatus(200);
        $data = $response->json('data') ?? $response->json();
        $paymentIds = collect($data)->pluck('id')->all();
        $this->assertNotContains($payment->id, $paymentIds);
    }
}
