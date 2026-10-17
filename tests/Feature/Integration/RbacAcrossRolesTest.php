<?php

namespace Tests\Feature\Integration;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\TestFactoryHelper;

/**
 * Tahap 20 — Poin 4: RBAC across roles — user without permission = 403.
 *
 * For each role (customer, store_owner, courier, store_staff, platform_admin),
 * verify that sensitive endpoints return 403 when the user lacks the
 * required permission.
 */
class RbacAcrossRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }


    /**
     * Sensitive endpoints and the minimum required permission.
     * Customer = baseline. Even platform_admin without manage_platform
     * cannot access owner-only areas.
     */
    public function sensitiveEndpoints(): array
    {
        return [
            'admin dashboard' => ['GET', '/api/admin/dashboard'],
            'admin payments' => ['GET', '/api/admin/payments'],
            'admin refunds' => ['GET', '/api/admin/refunds'],
            'admin disputes' => ['GET', '/api/admin/disputes'],
            'admin notifications' => ['GET', '/api/admin/notifications'],
            'admin audit' => ['GET', '/api/admin/audit'],
            'admin commission (owner-only)' => ['GET', '/api/admin/commission'],
            'admin security (owner-only)' => ['GET', '/api/admin/security'],
            'admin ai-usage (owner-only)' => ['GET', '/api/admin/ai-usage'],
            'courier dashboard' => ['GET', '/api/courier/dashboard'],
            'courier earnings' => ['GET', '/api/courier/earnings'],
            'store dashboard' => ['GET', '/api/store-owner/dashboard'],
        ];
    }

    public function test_unauthenticated_user_blocked_from_all_endpoints()
    {
        foreach ($this->sensitiveEndpoints() as $label => [$method, $url]) {
            $r = $this->{$method === 'GET' ? 'getJson' : 'postJson'}($url);
            $this->assertContains(
                $r->status(),
                [401, 403],
                "Endpoint {$label} ({$method} {$url}) must reject unauthenticated request (got {$r->status()})"
            );
        }
    }

    public function test_customer_blocked_from_all_sensitive_endpoints()
    {
        $customer = TestFactoryHelper::makeUser('customer');
        $this->actingAs($customer);

        foreach ($this->sensitiveEndpoints() as $label => [$method, $url]) {
            $r = $this->{$method === 'GET' ? 'getJson' : 'postJson'}($url);
            $this->assertEquals(
                403,
                $r->status(),
                "Customer must not access {$label} (got {$r->status()})"
            );
        }
    }

    public function test_courier_blocked_from_admin_and_store_endpoints()
    {
        $courier = TestFactoryHelper::makeUser('courier');
        $this->actingAs($courier);

        $blocked = [
            ['GET', '/api/admin/dashboard'],
            ['GET', '/api/admin/payments'],
            ['GET', '/api/admin/commission'],
            ['GET', '/api/store-owner/dashboard'],
            ['GET', '/api/store-owner/stores'],
        ];

        foreach ($blocked as [$method, $url]) {
            $r = $this->{$method === 'GET' ? 'getJson' : 'postJson'}($url);
            $this->assertEquals(403, $r->status(),
                "Courier must not access {$method} {$url} (got {$r->status()})");
        }
    }

    public function test_store_owner_blocked_from_admin_endpoints()
    {
        $owner = TestFactoryHelper::makeUser('store_owner');
        $this->actingAs($owner);

        $blocked = [
            '/api/admin/dashboard',
            '/api/admin/payments',
            '/api/admin/refunds',
            '/api/admin/disputes',
            '/api/admin/commission',
        ];

        foreach ($blocked as $url) {
            $r = $this->getJson($url);
            $this->assertEquals(403, $r->status(),
                "Store owner must not access {$url} (got {$r->status()})");
        }
    }

    public function test_platform_admin_blocked_from_owner_only_endpoints()
    {
        $admin = TestFactoryHelper::makeUser('platform_admin');
        $this->actingAs($admin);

        $blocked = [
            '/api/admin/commission',
            '/api/admin/security',
            '/api/admin/ai-usage',
        ];

        foreach ($blocked as $url) {
            $r = $this->getJson($url);
            $this->assertEquals(403, $r->status(),
                "Platform admin must not access owner-only {$url} (got {$r->status()})");
        }
    }

    public function test_platform_owner_can_access_all_admin_endpoints()
    {
        $owner = TestFactoryHelper::makeUser('platform_owner');
        $this->actingAs($owner);

        $allowed = [
            '/api/admin/dashboard',
            '/api/admin/payments',
            '/api/admin/refunds',
            '/api/admin/disputes',
            '/api/admin/notifications',
            '/api/admin/audit',
            '/api/admin/commission',
            '/api/admin/security',
            '/api/admin/ai-usage',
        ];

        foreach ($allowed as $url) {
            $r = $this->getJson($url);
            $this->assertEquals(200, $r->status(),
                "Platform owner must access {$url} (got {$r->status()})");
        }
    }
}