<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\ServiceCategory;
use App\Models\Store;
use App\Models\StoreService;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreOwnerRoleFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected string $adminToken;

    protected User $owner;
    protected string $ownerToken;
    protected Store $store1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $superAdminRole = Role::where('code', 'super_admin')->first();
        $customerRole = Role::where('code', 'customer')->first();

        // 1. Super Admin
        $this->superAdmin = User::create([
            'name' => 'Super Admin Platform',
            'email' => 'admin@laundryku.id',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserRole::create(['user_id' => $this->superAdmin->id, 'role_id' => $superAdminRole->id, 'status' => 'active']);
        $this->adminToken = $this->superAdmin->createToken('auth_token')->plainTextToken;

        // 2. Customer / Applicant
        $this->owner = User::create([
            'name' => 'Mitra Laundry Baru',
            'email' => 'mitra.baru@example.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->owner->id]);
        UserRole::create(['user_id' => $this->owner->id, 'role_id' => $customerRole->id, 'status' => 'active']);
        $this->ownerToken = $this->owner->createToken('auth_token')->plainTextToken;

        // 3. Store Application (Pending)
        $this->store1 = Store::create([
            'owner_user_id' => $this->owner->id,
            'name' => 'Laundry Berkah 1',
            'address_text' => 'Jl. Dago 10',
            'approval_status' => 'pending',
            'operational_status' => 'inactive',
        ]);
    }

    public function test_approve_assigns_store_admin_role_with_store_id_idempotently(): void
    {
        $storeAdminRole = Role::where('code', 'store_admin')->first();

        // Verify initial state: no store_admin role
        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $this->owner->id,
            'role_id' => $storeAdminRole->id,
            'store_id' => $this->store1->id,
        ]);

        // 1. Approve Store
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/admin/stores/' . $this->store1->id . '/approve');

        $response->assertStatus(200);

        // Verify role assigned with exact store_id
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $this->owner->id,
            'role_id' => $storeAdminRole->id,
            'store_id' => $this->store1->id,
            'status' => 'active',
        ]);

        // 2. Approve again (idempotent test)
        $secondResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/admin/stores/' . $this->store1->id . '/approve');

        $secondResponse->assertStatus(200);

        // Verify count does NOT duplicate
        $roleCount = UserRole::where('user_id', $this->owner->id)
            ->where('role_id', $storeAdminRole->id)
            ->where('store_id', $this->store1->id)
            ->count();

        $this->assertEquals(1, $roleCount);
    }

    public function test_reject_does_not_grant_store_admin_and_deactivates_if_prior(): void
    {
        $storeAdminRole = Role::where('code', 'store_admin')->first();

        // Reject pending store
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/admin/stores/' . $this->store1->id . '/reject', [
            'reason' => 'Dokumen NIB tidak valid atau buram.',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $this->owner->id,
            'role_id' => $storeAdminRole->id,
            'store_id' => $this->store1->id,
            'status' => 'active',
        ]);
    }

    public function test_owner_with_multiple_stores_receives_separate_roles_per_store(): void
    {
        $storeAdminRole = Role::where('code', 'store_admin')->first();

        $store2 = Store::create([
            'owner_user_id' => $this->owner->id,
            'name' => 'Laundry Berkah Cabang 2',
            'address_text' => 'Jl. Riau 20',
            'approval_status' => 'pending',
            'operational_status' => 'inactive',
        ]);

        // Approve Store 1
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson('/api/v1/admin/stores/' . $this->store1->id . '/approve')
            ->assertStatus(200);

        // Approve Store 2
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson('/api/v1/admin/stores/' . $store2->id . '/approve')
            ->assertStatus(200);

        // Verify owner has 2 distinct active user_roles for store_admin
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $this->owner->id,
            'role_id' => $storeAdminRole->id,
            'store_id' => $this->store1->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $this->owner->id,
            'role_id' => $storeAdminRole->id,
            'store_id' => $store2->id,
            'status' => 'active',
        ]);
    }

    public function test_sync_command_reconciles_missing_roles_idempotently(): void
    {
        $storeAdminRole = Role::where('code', 'store_admin')->first();

        // Simulate store approved directly without user_role
        $this->store1->update([
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $this->owner->id,
            'role_id' => $storeAdminRole->id,
            'store_id' => $this->store1->id,
        ]);

        // Run sync command
        $this->artisan('stores:sync-owner-roles')
            ->assertSuccessful();

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $this->owner->id,
            'role_id' => $storeAdminRole->id,
            'store_id' => $this->store1->id,
            'status' => 'active',
        ]);
    }

    public function test_suspend_store_blocks_new_orders_while_existing_orders_remain(): void
    {
        // 1. Approve store
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson('/api/v1/admin/stores/' . $this->store1->id . '/approve');

        // Create service
        $cat = ServiceCategory::create(['name' => 'Kiloan', 'slug' => 'kiloan', 'status' => 'active']);
        $service = StoreService::create([
            'store_id' => $this->store1->id,
            'category_id' => $cat->id,
            'name' => 'Cuci Kiloan',
            'pricing_unit' => 'kg',
            'price_per_unit' => 7000,
            'estimated_duration_hours' => 24,
            'is_active' => true,
        ]);

        // Customer creates an order
        $customer = User::create(['name' => 'Cust 1', 'email' => 'c1@ex.com', 'status' => 'active']);
        $customerToken = $customer->createToken('t')->plainTextToken;

        $existingOrder = Order::create([
            'order_number' => 'ORD-EXIST-01',
            'customer_id' => $customer->id,
            'store_id' => $this->store1->id,
            'pickup_address_snapshot' => ['address' => 'Jl. A'],
            'delivery_address_snapshot' => ['address' => 'Jl. A'],
            'status' => 'PROCESSING',
            'payment_status' => 'PAID',
            'estimated_amount' => 21000,
            'final_amount' => 21000,
        ]);

        // 2. Suspend store
        $suspendRes = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson('/api/v1/admin/stores/' . $this->store1->id . '/suspend', [
                'reason' => 'Banyak komplain keterlambatan pengantaran.',
            ]);
        $suspendRes->assertStatus(200);

        $this->assertEquals('suspended', Store::find($this->store1->id)->operational_status);

        // 3. New order creation is BLOCKED
        $orderRes = $this->withHeaders(['Authorization' => 'Bearer ' . $customerToken])
            ->postJson('/api/v1/orders', [
                'store_id' => $this->store1->id,
                'items' => [['service_id' => $service->id, 'quantity' => 2.0]],
                'pickup_address' => ['address_text' => 'Jl. B', 'latitude' => -6.9, 'longitude' => 107.6],
            ]);

        $orderRes->assertStatus(404);

        // 4. Existing order still exists and can be queried
        $this->assertDatabaseHas('orders', ['id' => $existingOrder->id, 'status' => 'PROCESSING']);
    }

    public function test_me_endpoint_returns_stores_and_active_roles(): void
    {
        // Approve store
        $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/admin/stores/' . $this->store1->id . '/approve')
            ->assertStatus(200);

        // Fetch /me as store owner
        $meRes = $this->actingAs($this->owner)
            ->getJson('/api/v1/me');

        $meRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($meRes->json('data.stores'));
        $this->assertEquals($this->store1->id, $meRes->json('data.stores.0.id'));
        $this->assertEquals('approved', $meRes->json('data.stores.0.approval_status'));

        $roles = $meRes->json('data.roles');
        $this->assertContains('store_admin', $roles);
    }
}
