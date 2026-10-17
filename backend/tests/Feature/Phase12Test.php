<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\ServiceCategory;
use App\Models\Store;
use App\Models\StoreService;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase12Test extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $customer;
    protected User $storeOwner;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $superAdminRole = Role::firstOrCreate(['id' => 4, 'code' => 'super_admin'], ['name' => 'Super Admin']);
        $storeAdminRole = Role::firstOrCreate(['id' => 3, 'code' => 'store_admin'], ['name' => 'Admin Toko']);
        $customerRole = Role::firstOrCreate(['id' => 1, 'code' => 'customer'], ['name' => 'Customer']);

        // Create Super Admin
        $this->superAdmin = User::factory()->create([
            'email' => 'superadmin@laundryku.id',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->superAdmin->id]);
        UserRole::create(['user_id' => $this->superAdmin->id, 'role_id' => $superAdminRole->id, 'status' => 'active']);

        // Create Customer
        $this->customer = User::factory()->create(['status' => 'active', 'verified_at' => now()]);
        UserProfile::create(['user_id' => $this->customer->id]);
        UserRole::create(['user_id' => $this->customer->id, 'role_id' => $customerRole->id, 'status' => 'active']);

        // Create Store Owner
        $this->storeOwner = User::factory()->create(['status' => 'active', 'verified_at' => now()]);
        UserProfile::create(['user_id' => $this->storeOwner->id]);
        UserRole::create(['user_id' => $this->storeOwner->id, 'role_id' => $storeAdminRole->id, 'status' => 'active']);

        // Create Default Category
        $cat = ServiceCategory::create(['name' => 'Kiloan Reguler', 'slug' => 'kiloan-reguler', 'status' => 'active']);

        // Create Store
        $this->store = Store::create([
            'owner_user_id' => $this->storeOwner->id,
            'name' => 'Berkah Laundry',
            'address_text' => 'Jl. Mawar No. 10',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'service_radius_km' => 10.0,
            'approval_status' => 'pending',
            'operational_status' => 'inactive',
        ]);

        StoreService::create([
            'store_id' => $this->store->id,
            'category_id' => $cat->id,
            'name' => 'Cuci Lipat Reguler',
            'pricing_unit' => 'kg',
            'price_per_unit' => 7000,
            'minimum_charge' => 0,
            'is_active' => true,
        ]);

        // Default Platform Setting
        PlatformSetting::updateOrCreate(
            ['key' => 'platform_commission_flat'],
            [
                'value_json' => ['amount' => 1000],
                'description' => 'Besaran komisi flat platform',
            ]
        );
    }

    /**
     * 1. Authorization: Non-admin users cannot access admin endpoints.
     */
    public function test_non_super_admin_cannot_access_admin_routes(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/metrics');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * 2. Store Approval: Super admin can approve pending store, records audit log.
     */
    public function test_super_admin_can_approve_store(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/admin/stores/{$this->store->id}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('data.approval_status', 'approved')
            ->assertJsonPath('data.operational_status', 'active');

        $this->assertDatabaseHas('stores', [
            'id' => $this->store->id,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'APPROVE_STORE',
            'entity_type' => 'stores',
            'entity_id' => $this->store->id,
        ]);
    }

    /**
     * 3. Store Rejection: Mandatory reason required, records audit log.
     */
    public function test_super_admin_can_reject_store_with_mandatory_reason(): void
    {
        // Rejection without reason fails
        $failResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/admin/stores/{$this->store->id}/reject", []);

        $failResponse->assertStatus(422);

        // Rejection with reason succeeds
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/admin/stores/{$this->store->id}/reject", [
                'reason' => 'Foto outlet dan dokumen izin usaha tidak jelas.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.approval_status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Foto outlet dan dokumen izin usaha tidak jelas.');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'REJECT_STORE',
            'entity_type' => 'stores',
            'entity_id' => $this->store->id,
        ]);
    }

    /**
     * 4. Order constraint: Customer cannot order from unapproved or rejected store.
     */
    public function test_unapproved_store_cannot_receive_orders(): void
    {
        $service = $this->store->services()->first();

        $payload = [
            'store_id' => $this->store->id,
            'pickup_address' => [
                'address_text' => 'Jl. Melati No. 5',
                'latitude' => -6.200100,
                'longitude' => 106.816700,
            ],
            'items' => [
                ['service_id' => $service->id, 'quantity' => 3.0],
            ],
        ];

        // Store is pending
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/orders', $payload);

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'STORE_NOT_AVAILABLE');

        // Approve store
        $this->store->update(['approval_status' => 'approved', 'operational_status' => 'active']);

        // Now order succeeds
        $successResponse = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/orders', $payload);

        $successResponse->assertStatus(201);
    }

    /**
     * 5. User Account Suspension & Ban: Mandatory reason, revokes tokens, records audit log.
     */
    public function test_super_admin_can_suspend_or_ban_user(): void
    {
        // Missing reason when suspending fails
        $failResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->patchJson("/api/v1/admin/users/{$this->customer->id}/status", [
                'status' => 'suspended',
            ]);

        $failResponse->assertStatus(422);

        // Suspend with reason succeeds
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->patchJson("/api/v1/admin/users/{$this->customer->id}/status", [
                'status' => 'suspended',
                'reason' => 'Terindikasi melakukan kecurangan transaksi.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'suspended')
            ->assertJsonPath('data.status_reason', 'Terindikasi melakukan kecurangan transaksi.');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE_USER_STATUS',
            'entity_type' => 'users',
            'entity_id' => $this->customer->id,
        ]);
    }

    /**
     * 6. Service Categories Master CRUD.
     */
    public function test_super_admin_crud_service_categories(): void
    {
        // Create
        $createRes = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Cuci Sepatu & Helm',
                'slug' => 'cuci-sepatu-helm',
                'status' => 'active',
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('data.name', 'Cuci Sepatu & Helm');

        $catId = $createRes->json('data.id');

        // Update
        $updateRes = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/v1/admin/categories/{$catId}", [
                'name' => 'Cuci Sepatu, Tas & Helm',
                'status' => 'active',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.name', 'Cuci Sepatu, Tas & Helm');

        // Delete
        $deleteRes = $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson("/api/v1/admin/categories/{$catId}");

        $deleteRes->assertStatus(200);
        $this->assertDatabaseMissing('service_categories', ['id' => $catId]);
    }

    /**
     * 7. Commission update & older order snapshot immutability test.
     * Order 1 created at commission Rp1.000 -> commission is changed to Rp2.500 ->
     * Order 1 STILL has commission Rp1.000 -> Order 2 has commission Rp2.500.
     */
    public function test_platform_commission_update_preserves_old_order_snapshot(): void
    {
        $this->store->update(['approval_status' => 'approved', 'operational_status' => 'active']);
        $service = $this->store->services()->first();

        // 1. Create initial order when commission is 1000
        $order1Res = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'store_id' => $this->store->id,
                'pickup_address' => [
                    'address_text' => 'Jl. Thamrin No. 1',
                    'latitude' => -6.200000,
                    'longitude' => 106.816666,
                ],
                'items' => [['service_id' => $service->id, 'quantity' => 2.0]],
            ]);

        $order1Res->assertStatus(201);
        $order1Id = $order1Res->json('data.id');
        $this->assertEquals(1000, $order1Res->json('data.commission_amount'));

        // 2. Super Admin updates platform commission to Rp 2.500
        $settingRes = $this->actingAs($this->superAdmin, 'sanctum')
            ->patchJson('/api/v1/admin/platform-settings/commission', [
                'amount' => 2500,
                'description' => 'Penyesuaian komisi platform flat 2026',
            ]);

        $settingRes->assertStatus(200)
            ->assertJsonPath('data.amount', 2500);

        // 3. Create second order after commission update
        $order2Res = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'store_id' => $this->store->id,
                'pickup_address' => [
                    'address_text' => 'Jl. Sudirman No. 2',
                    'latitude' => -6.200000,
                    'longitude' => 106.816666,
                ],
                'items' => [['service_id' => $service->id, 'quantity' => 4.0]],
            ]);

        $order2Res->assertStatus(201);
        $order2Id = $order2Res->json('data.id');
        $this->assertEquals(2500, $order2Res->json('data.commission_amount'));

        // 4. Verify in DB: Order 1 REMAINS 1000, Order 2 IS 2500
        $order1 = Order::find($order1Id);
        $order2 = Order::find($order2Id);

        $this->assertEquals(1000, $order1->commission_amount, 'Order 1 snapshot must preserve 1000');
        $this->assertEquals(2500, $order2->commission_amount, 'Order 2 snapshot must take new 2500');

        // 5. Verify audit log was recorded for commission update
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE_COMMISSION',
            'entity_type' => 'platform_settings',
        ]);
    }

    /**
     * 8. Dashboard metrics endpoint.
     */
    public function test_super_admin_can_view_monitoring_metrics(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/metrics?period=all_time');

        $response->assertStatus(200)
            ->assertJsonStructure([
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
                    ],
                    'orders_by_status',
                    'users_by_role',
                    'recent_logs',
                ],
            ]);
    }

    /**
     * 9. Audit Logs listing & filter.
     */
    public function test_super_admin_can_view_and_filter_audit_logs(): void
    {
        // Trigger an audit log
        AuditLog::record(
            actor: $this->superAdmin,
            action: 'TEST_ACTION',
            entityType: 'test_entity',
            entityId: '123',
            metadata: ['password' => 'secret123', 'info' => 'test data']
        );

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/audit-logs?action=TEST_ACTION');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.action', 'TEST_ACTION')
            ->assertJsonPath('data.0.metadata.password', '[REDACTED]'); // Sensitive fields sanitized
    }
}
