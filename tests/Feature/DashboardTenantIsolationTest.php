<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\StoreStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tahap 18 — Dashboard tenant isolation + role-based menu authorization.
 *
 * Verifies:
 *  - Store A owner cannot read Store B's dashboard (cross-tenant = 403).
 *  - Customer role (no permission) gets 403 on dashboard endpoints.
 *  - Owner-only endpoints (commission/security/ai-usage) return 403
 *    for platform_admin.
 *  - Admin endpoints return 200 for admin role.
 */
class DashboardTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected User $ownerA;
    protected User $ownerB;
    protected User $platformAdmin;
    protected User $platformOwner;
    protected User $customer;
    protected Store $storeA;
    protected Store $storeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // Two distinct store owners
        $this->ownerA = User::factory()->create();
        $this->ownerA->roles()->attach(Role::where('name', 'store_owner')->first());

        $this->ownerB = User::factory()->create();
        $this->ownerB->roles()->attach(Role::where('name', 'store_owner')->first());

        $this->platformAdmin = User::factory()->create();
        $this->platformAdmin->roles()->attach(Role::where('name', 'platform_admin')->first());

        $this->platformOwner = User::factory()->create();
        $this->platformOwner->roles()->attach(Role::where('name', 'platform_owner')->first());

        $this->customer = User::factory()->create();
        $this->customer->roles()->attach(Role::where('name', 'customer')->first());

        $this->storeA = Store::factory()->create([
            'owner_user_id' => $this->ownerA->id,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->storeB = Store::factory()->create([
            'owner_user_id' => $this->ownerB->id,
            'status' => 'approved',
            'is_active' => true,
        ]);
    }

    // ========== TENANT ISOLATION ==========

    public function test_owner_a_cannot_view_owner_b_dashboard()
    {
        $this->actingAs($this->ownerA);

        // Owner A explicitly tries to read Store B's dashboard.
        $response = $this->getJson("/api/store-owner/dashboard?store_id={$this->storeB->id}");

        $response->assertStatus(403);
        $this->assertStringContainsString('do not have access', strtolower($response->getContent()));
    }

    public function test_owner_a_can_view_own_dashboard()
    {
        $this->actingAs($this->ownerA);

        $response = $this->getJson("/api/store-owner/dashboard?store_id={$this->storeA->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'store' => ['id', 'name'],
            'menu',
            'metrics' => ['orders_today', 'revenue_today'],
        ]);
        $this->assertEquals($this->storeA->id, $response->json('store.id'));
    }

    public function test_owner_a_cannot_view_owner_b_services_endpoint()
    {
        $this->actingAs($this->ownerA);

        $response = $this->getJson("/api/store-owner/dashboard/services?store_id={$this->storeB->id}");

        $response->assertStatus(403);
    }

    public function test_owner_a_cannot_view_owner_b_reports_endpoint()
    {
        $this->actingAs($this->ownerA);

        $response = $this->getJson("/api/store-owner/dashboard/reports?store_id={$this->storeB->id}");

        $response->assertStatus(403);
    }

    public function test_staff_of_store_a_cannot_view_store_b_dashboard()
    {
        $staffOfA = User::factory()->create();
        $staffOfA->roles()->attach(Role::where('name', 'store_staff')->first());
        StoreStaff::create([
            'store_id' => $this->storeA->id,
            'user_id' => $staffOfA->id,
            'is_active' => true,
        ]);

        $this->actingAs($staffOfA);

        $response = $this->getJson("/api/store-owner/dashboard?store_id={$this->storeB->id}");

        $response->assertStatus(403);
    }

    // ========== PERMISSION CHECKS (no perm = 403) ==========

    public function test_customer_role_cannot_access_store_dashboard()
    {
        $this->actingAs($this->customer);

        $response = $this->getJson('/api/store-owner/dashboard');

        $response->assertStatus(403);
    }

    public function test_customer_role_cannot_access_admin_dashboard()
    {
        $this->actingAs($this->customer);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(403);
    }

    public function test_store_owner_cannot_access_admin_dashboard()
    {
        $this->actingAs($this->ownerA);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_dashboard()
    {
        $this->actingAs($this->platformAdmin);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'stats',
            'menu',
            'is_owner',
        ]);
        // platform_admin DOES NOT have manage_platform permission
        $this->assertFalse($response->json('is_owner'));
    }

    public function test_owner_can_access_admin_dashboard_with_is_owner_true()
    {
        $this->actingAs($this->platformOwner);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
        $this->assertTrue($response->json('is_owner'));
    }

    public function test_admin_cannot_access_owner_only_commission_endpoint()
    {
        $this->actingAs($this->platformAdmin);

        $response = $this->getJson('/api/admin/commission');

        $response->assertStatus(403);
    }

    public function test_admin_cannot_access_owner_only_security_endpoint()
    {
        $this->actingAs($this->platformAdmin);

        $response = $this->getJson('/api/admin/security');

        $response->assertStatus(403);
    }

    public function test_admin_cannot_access_owner_only_ai_usage_endpoint()
    {
        $this->actingAs($this->platformAdmin);

        $response = $this->getJson('/api/admin/ai-usage');

        $response->assertStatus(403);
    }

    public function test_owner_can_access_all_owner_only_endpoints()
    {
        $this->actingAs($this->platformOwner);

        $this->getJson('/api/admin/commission')->assertStatus(200);
        $this->getJson('/api/admin/security')->assertStatus(200);
        $this->getJson('/api/admin/ai-usage')->assertStatus(200);
    }

    // ========== MENU FILTERING ==========

    public function test_admin_menu_excludes_owner_only_items()
    {
        $this->actingAs($this->platformAdmin);

        $response = $this->getJson('/api/admin/menu')->assertStatus(200);

        $menuKeys = array_column($response->json('menu'), 'key');

        $this->assertNotContains('commission', $menuKeys);
        $this->assertNotContains('security', $menuKeys);
        $this->assertNotContains('ai_usage', $menuKeys);
        $this->assertContains('stores', $menuKeys);
        $this->assertContains('payments', $menuKeys);
    }

    public function test_owner_menu_includes_owner_only_items()
    {
        $this->actingAs($this->platformOwner);

        $response = $this->getJson('/api/admin/menu')->assertStatus(200);

        $menuKeys = array_column($response->json('menu'), 'key');

        $this->assertContains('commission', $menuKeys);
        $this->assertContains('security', $menuKeys);
        $this->assertContains('ai_usage', $menuKeys);
    }

    // ========== SENSITIVE DATA FILTERING ==========

    public function test_store_staff_does_not_see_settlement_amount()
    {
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('name', 'store_staff')->first());
        StoreStaff::create([
            'store_id' => $this->storeA->id,
            'user_id' => $staff->id,
            'is_active' => true,
        ]);

        $this->actingAs($staff);

        $response = $this->getJson("/api/store-owner/dashboard?store_id={$this->storeA->id}")
            ->assertStatus(200);

        // store_staff tidak boleh lihat settlement amount.
        $this->assertNull($response->json('metrics.settlement_pending_amount'));
    }

    public function test_store_owner_sees_settlement_amount()
    {
        $this->actingAs($this->ownerA);

        $response = $this->getJson("/api/store-owner/dashboard?store_id={$this->storeA->id}")
            ->assertStatus(200);

        // store_owner boleh lihat settlement amount (numeric or null, bukan denied).
        $value = $response->json('metrics.settlement_pending_amount');
        $this->assertTrue($value === null || is_numeric($value));
    }
}