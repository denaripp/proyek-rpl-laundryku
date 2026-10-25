<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_unauthenticated_user_cannot_access_dashboard_metrics(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard/metrics');
        $response->assertStatus(401);
    }

    public function test_customer_cannot_access_dashboard_metrics(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        Sanctum::actingAs($customer, ['role:customer']);

        $response = $this->getJson('/api/v1/admin/dashboard/metrics');
        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_dashboard_metrics_with_valid_structure(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin, ['role:super_admin']);

        $response = $this->getJson('/api/v1/admin/dashboard/metrics?period=all_time');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
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
                'payments_by_status',
                'recent_orders',
                'recent_logs',
            ],
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('all_time', $response->json('data.period'));
    }
}
