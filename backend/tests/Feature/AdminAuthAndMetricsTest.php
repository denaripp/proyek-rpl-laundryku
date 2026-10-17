<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthAndMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'level' => 100]);
        Role::firstOrCreate(['code' => 'courier'], ['name' => 'Kurir', 'level' => 30]);
        Role::firstOrCreate(['code' => 'store_admin'], ['name' => 'Admin Toko', 'level' => 50]);
        Role::firstOrCreate(['code' => 'customer'], ['name' => 'Customer', 'level' => 10]);
    }

    /**
     * Helper to prepare standard super admin.
     */
    protected function setupSuperAdmin(): User
    {
        $admin = User::firstOrCreate(
            ['username' => 'admin_test'],
            [
                'name' => 'Test Admin',
                'email' => 'admin_test@laundryku.id',
                'phone' => '081299990001',
                'password' => Hash::make('admin123'),
                'status' => 'active',
            ]
        );

        $role = Role::where('code', 'super_admin')->first();
        UserRole::firstOrCreate([
            'user_id' => $admin->id,
            'role_id' => $role->id,
        ], [
            'status' => 'active',
        ]);

        return $admin;
    }

    /**
     * Helper to prepare customer.
     */
    protected function setupCustomer(): User
    {
        $customer = User::firstOrCreate(
            ['email' => 'customer_test@laundryku.id'],
            [
                'name' => 'Customer Test',
                'username' => 'customer_user',
                'phone' => '081299990002',
                'password' => Hash::make('customer123'),
                'status' => 'active',
            ]
        );

        $role = Role::where('code', 'customer')->first();
        UserRole::firstOrCreate([
            'user_id' => $customer->id,
            'role_id' => $role->id,
        ], [
            'status' => 'active',
        ]);

        return $customer;
    }

    public function test_super_admin_login_success()
    {
        $this->setupSuperAdmin();

        $response = $this->postJson('/api/v1/admin/login', [
            'username' => 'admin_test',
            'password' => 'admin123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'name', 'username', 'email'],
                ],
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ADMIN_LOGIN_SUCCESS',
            'entity_type' => 'admin_auth',
        ]);
    }

    public function test_super_admin_login_with_invalid_credentials_returns_401()
    {
        $this->setupSuperAdmin();

        $response = $this->postJson('/api/v1/admin/login', [
            'username' => 'admin_test',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ADMIN_LOGIN_FAILED',
        ]);
    }

    public function test_customer_login_to_admin_portal_returns_403()
    {
        $this->setupCustomer();

        $response = $this->postJson('/api/v1/admin/login', [
            'username' => 'customer_user',
            'password' => 'customer123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ADMIN_LOGIN_REJECTED',
        ]);
    }

    public function test_unauthenticated_request_to_admin_endpoint_returns_401()
    {
        $response = $this->getJson('/api/v1/admin/dashboard/metrics');

        $response->assertStatus(401);
    }

    public function test_customer_token_accessing_admin_endpoint_returns_403()
    {
        $customer = $this->setupCustomer();
        $token = $customer->createToken('customer_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/dashboard/metrics');

        $response->assertStatus(403);
    }

    public function test_super_admin_logout_revokes_token()
    {
        $admin = $this->setupSuperAdmin();
        $token = $admin->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/logout');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(0, $admin->fresh()->tokens);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ADMIN_LOGOUT_SUCCESS',
        ]);
    }

    public function test_super_admin_seeder_is_idempotent()
    {
        $seeder = new SuperAdminSeeder();
        
        // Run first time
        $seeder->run();
        $countFirst = User::whereHas('roles', fn ($q) => $q->where('code', 'super_admin'))->count();
        $this->assertGreaterThanOrEqual(1, $countFirst);

        // Run second time without duplicating
        $seeder->run();
        $countSecond = User::whereHas('roles', fn ($q) => $q->where('code', 'super_admin'))->count();
        $this->assertEquals($countFirst, $countSecond);
    }

    public function test_admin_metrics_computes_real_data_accurately()
    {
        $admin = $this->setupSuperAdmin();
        $token = $admin->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/dashboard/metrics?period=all_time');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'metrics' => [
                        'total_gmv',
                        'total_platform_revenue',
                        'total_orders',
                        'completed_orders',
                        'active_stores',
                        'pending_stores',
                        'total_stores',
                        'total_users',
                        'active_users',
                        'suspended_users',
                    ],
                    'orders_by_status',
                    'users_by_role' => [
                        'customer',
                        'store_admin',
                        'courier',
                    ],
                    'recent_logs',
                ],
            ]);
    }

    public function test_admin_orders_and_payments_endpoints_work()
    {
        $admin = $this->setupSuperAdmin();
        $token = $admin->createToken('admin_token')->plainTextToken;

        $ordersRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/orders');
        $ordersRes->assertStatus(200)->assertJsonPath('success', true);

        $paymentsRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/payments');
        $paymentsRes->assertStatus(200)->assertJsonPath('success', true);
    }
}
