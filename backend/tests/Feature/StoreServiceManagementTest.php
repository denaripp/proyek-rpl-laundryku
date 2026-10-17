<?php

namespace Tests\Feature;

use App\Models\AuditLog;
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

class StoreServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $storeOwnerA;
    protected string $tokenA;
    protected Store $storeA;

    protected User $storeOwnerB;
    protected string $tokenB;
    protected Store $storeB;

    protected User $customer;
    protected string $customerToken;

    protected ServiceCategory $catKiloan;
    protected ServiceCategory $catSatuan;
    protected ServiceCategory $catInactive;

    protected StoreService $serviceKiloanA;
    protected StoreService $serviceSatuanA;
    protected StoreService $serviceKiloanB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $customerRole = Role::where('code', 'customer')->first();
        $storeAdminRole = Role::where('code', 'store_admin')->first();

        // 1. Categories
        $this->catKiloan = ServiceCategory::create([
            'name' => 'Kiloan Reguler',
            'slug' => 'kiloan-reguler',
            'status' => 'active',
        ]);

        $this->catSatuan = ServiceCategory::create([
            'name' => 'Cuci Satuan & Dry Clean',
            'slug' => 'cuci-satuan',
            'status' => 'active',
        ]);

        $this->catInactive = ServiceCategory::create([
            'name' => 'Kategori Nonaktif',
            'slug' => 'kategori-nonaktif',
            'status' => 'inactive',
        ]);

        // 2. Store Owner A (Laundry Berkah A)
        $this->storeOwnerA = User::create([
            'name' => 'Pemilik Toko A',
            'email' => 'owner.a@example.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->storeOwnerA->id]);
        UserRole::create(['user_id' => $this->storeOwnerA->id, 'role_id' => $customerRole->id, 'status' => 'active']);
        $this->tokenA = $this->storeOwnerA->createToken('auth_token')->plainTextToken;

        $this->storeA = Store::create([
            'owner_user_id' => $this->storeOwnerA->id,
            'name' => 'Laundry Berkah A',
            'address_text' => 'Jl. Merdeka No. 1, Bandung',
            'latitude' => -6.9175,
            'longitude' => 107.6191,
            'service_radius_km' => 5.0,
            'approval_status' => 'approved',
            'operational_status' => 'active',
            'approved_at' => now(),
        ]);
        UserRole::create([
            'user_id' => $this->storeOwnerA->id,
            'role_id' => $storeAdminRole->id,
            'store_id' => $this->storeA->id,
            'status' => 'active',
        ]);

        // Services for Store A
        $this->serviceKiloanA = StoreService::create([
            'store_id' => $this->storeA->id,
            'category_id' => $this->catKiloan->id,
            'name' => 'Cuci Kering Setrika Kiloan',
            'description' => 'Cuci bersih wangi dan setrika rapi',
            'pricing_unit' => 'kg',
            'price_per_unit' => 7000,
            'minimum_charge' => 14000,
            'estimated_duration_hours' => 24,
            'is_active' => true,
        ]);

        $this->serviceSatuanA = StoreService::create([
            'store_id' => $this->storeA->id,
            'category_id' => $this->catSatuan->id,
            'name' => 'Cuci Jas / Blazer',
            'description' => 'Dry clean khusus pakaian formal',
            'pricing_unit' => 'item',
            'price_per_unit' => 25000,
            'minimum_charge' => 0,
            'estimated_duration_hours' => 48,
            'is_active' => true,
        ]);

        // 3. Store Owner B (Laundry Bersih B)
        $this->storeOwnerB = User::create([
            'name' => 'Pemilik Toko B',
            'email' => 'owner.b@example.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->storeOwnerB->id]);
        UserRole::create(['user_id' => $this->storeOwnerB->id, 'role_id' => $customerRole->id, 'status' => 'active']);
        $this->tokenB = $this->storeOwnerB->createToken('auth_token')->plainTextToken;

        $this->storeB = Store::create([
            'owner_user_id' => $this->storeOwnerB->id,
            'name' => 'Laundry Bersih B',
            'address_text' => 'Jl. Asia Afrika No. 20, Bandung',
            'latitude' => -6.9200,
            'longitude' => 107.6100,
            'service_radius_km' => 5.0,
            'approval_status' => 'approved',
            'operational_status' => 'active',
            'approved_at' => now(),
        ]);
        UserRole::create([
            'user_id' => $this->storeOwnerB->id,
            'role_id' => $storeAdminRole->id,
            'store_id' => $this->storeB->id,
            'status' => 'active',
        ]);

        $this->serviceKiloanB = StoreService::create([
            'store_id' => $this->storeB->id,
            'category_id' => $this->catKiloan->id,
            'name' => 'Cuci Kiloan Toko B',
            'pricing_unit' => 'kg',
            'price_per_unit' => 8000,
            'minimum_charge' => 16000,
            'estimated_duration_hours' => 24,
            'is_active' => true,
        ]);

        // 4. Regular Customer
        $this->customer = User::create([
            'name' => 'Budi Customer',
            'email' => 'budi@example.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->customer->id]);
        UserRole::create(['user_id' => $this->customer->id, 'role_id' => $customerRole->id, 'status' => 'active']);
        $this->customerToken = $this->customer->createToken('auth_token')->plainTextToken;
    }

    public function test_store_admin_can_list_and_filter_services(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
        ])->getJson('/api/v1/store/services?search=Kiloan');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Cuci Kering Setrika Kiloan');
    }

    public function test_store_admin_can_create_new_service_with_validation_and_audit(): void
    {
        $payload = [
            'category_id' => $this->catKiloan->id,
            'name' => 'Cuci Lipat Kiloan Express',
            'description' => 'Cuci kering lipat selesai dalam 12 jam',
            'pricing_unit' => 'kg',
            'price_per_unit' => 10000,
            'minimum_charge' => 20000,
            'estimated_duration_hours' => 12,
            'is_active' => true,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
        ])->postJson('/api/v1/store/services', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Cuci Lipat Kiloan Express')
            ->assertJsonPath('data.description', 'Cuci kering lipat selesai dalam 12 jam');

        $this->assertDatabaseHas('store_services', [
            'store_id' => $this->storeA->id,
            'name' => 'Cuci Lipat Kiloan Express',
            'price_per_unit' => 10000,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'STORE_SERVICE_CREATED',
            'entity_type' => 'store_services',
            'actor_id' => $this->storeOwnerA->id,
        ]);
    }

    public function test_negative_price_or_invalid_duration_is_rejected(): void
    {
        $payload = [
            'category_id' => $this->catKiloan->id,
            'name' => 'Layanan Harga Negatif',
            'pricing_unit' => 'kg',
            'price_per_unit' => -5000,
            'minimum_charge' => -1000,
            'estimated_duration_hours' => 200, // Max 168
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
        ])->postJson('/api/v1/store/services', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['error' => ['details' => ['price_per_unit', 'minimum_charge', 'estimated_duration_hours']]]);
    }

    public function test_duplicate_service_name_on_same_store_is_rejected(): void
    {
        $payload = [
            'category_id' => $this->catKiloan->id,
            'name' => 'Cuci Kering Setrika Kiloan', // Existing in Store A
            'pricing_unit' => 'kg',
            'price_per_unit' => 8000,
            'estimated_duration_hours' => 24,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
        ])->postJson('/api/v1/store/services', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    }

    public function test_inactive_category_is_rejected(): void
    {
        $payload = [
            'category_id' => $this->catInactive->id,
            'name' => 'Layanan Kategori Nonaktif',
            'pricing_unit' => 'kg',
            'price_per_unit' => 8000,
            'estimated_duration_hours' => 24,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
        ])->postJson('/api/v1/store/services', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['error' => ['details' => ['category_id']]]);
    }

    public function test_tenant_isolation_store_a_cannot_modify_or_delete_store_b_service(): void
    {
        // Store A attempts to update Store B's service
        $updateRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
        ])->patchJson('/api/v1/store/services/' . $this->serviceKiloanB->id, [
            'name' => 'Hacked Name',
        ]);
        $updateRes->assertStatus(403);

        // Store A attempts to delete Store B's service
        $deleteRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
        ])->deleteJson('/api/v1/store/services/' . $this->serviceKiloanB->id);
        $deleteRes->assertStatus(403);

        // Store A attempts to toggle status of Store B's service
        $statusRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
        ])->patchJson('/api/v1/store/services/' . $this->serviceKiloanB->id . '/status', [
            'is_active' => false,
        ]);
        $statusRes->assertStatus(403);
    }

    public function test_customer_without_store_cannot_access_store_services_endpoint(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->getJson('/api/v1/store/services');

        $response->assertStatus(404);
    }

    public function test_delete_service_already_used_in_orders_is_rejected(): void
    {
        // Create an order referencing serviceKiloanA
        $order = Order::create([
            'order_number' => 'ORD-TEST-001',
            'customer_id' => $this->customer->id,
            'store_id' => $this->storeA->id,
            'pickup_address_snapshot' => ['address' => 'Jl. Pelanggan 1'],
            'delivery_address_snapshot' => ['address' => 'Jl. Pelanggan 1'],
            'status' => 'PENDING_PICKUP',
            'payment_status' => 'UNPAID',
            'estimated_amount' => 21000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'service_id' => $this->serviceKiloanA->id,
            'service_name_snapshot' => $this->serviceKiloanA->name,
            'pricing_unit_snapshot' => $this->serviceKiloanA->pricing_unit,
            'unit_price_snapshot' => $this->serviceKiloanA->price_per_unit,
            'minimum_charge_snapshot' => $this->serviceKiloanA->minimum_charge,
            'quantity_estimated' => 3.0,
            'line_estimated_amount' => 21000,
        ]);

        // Attempt deletion
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
        ])->deleteJson('/api/v1/store/services/' . $this->serviceKiloanA->id);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'CANNOT_DELETE_USED_SERVICE');

        // Verify service still exists in database
        $this->assertDatabaseHas('store_services', ['id' => $this->serviceKiloanA->id]);
    }

    public function test_delete_service_not_used_in_orders_succeeds(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
        ])->deleteJson('/api/v1/store/services/' . $this->serviceSatuanA->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('store_services', ['id' => $this->serviceSatuanA->id]);
    }

    public function test_bulk_status_update_works_and_rejects_foreign_services(): void
    {
        // 1. Success bulk deactivate store A services
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
        ])->postJson('/api/v1/store/services/bulk-status', [
            'ids' => [$this->serviceKiloanA->id, $this->serviceSatuanA->id],
            'is_active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.updated_count', 2)
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse(StoreService::find($this->serviceKiloanA->id)->is_active);
        $this->assertFalse(StoreService::find($this->serviceSatuanA->id)->is_active);

        // 2. Reject if array includes service belonging to Store B
        $foreignResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
        ])->postJson('/api/v1/store/services/bulk-status', [
            'ids' => [$this->serviceKiloanA->id, $this->serviceKiloanB->id],
            'is_active' => true,
        ]);

        $foreignResponse->assertStatus(403);
    }

    public function test_inactive_service_cannot_be_ordered_by_customer(): void
    {
        // Deactivate serviceKiloanA
        $this->serviceKiloanA->update(['is_active' => false]);

        $payload = [
            'store_id' => $this->storeA->id,
            'items' => [
                [
                    'service_id' => $this->serviceKiloanA->id,
                    'quantity' => 3.0,
                ],
            ],
            'pickup_address_snapshot' => ['address' => 'Jl. Pelanggan 1'],
            'delivery_address_snapshot' => ['address' => 'Jl. Pelanggan 1'],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->postJson('/api/v1/orders', $payload);

        $response->assertStatus(422);
    }

    public function test_price_change_does_not_affect_existing_order_snapshot(): void
    {
        // 1. Create order when service is Rp7.000/kg
        $order = Order::create([
            'order_number' => 'ORD-TEST-002',
            'customer_id' => $this->customer->id,
            'store_id' => $this->storeA->id,
            'pickup_address_snapshot' => ['address' => 'Jl. Pelanggan 1'],
            'delivery_address_snapshot' => ['address' => 'Jl. Pelanggan 1'],
            'status' => 'PENDING_PICKUP',
            'payment_status' => 'UNPAID',
            'estimated_amount' => 21000,
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'service_id' => $this->serviceKiloanA->id,
            'service_name_snapshot' => $this->serviceKiloanA->name,
            'pricing_unit_snapshot' => $this->serviceKiloanA->pricing_unit,
            'unit_price_snapshot' => $this->serviceKiloanA->price_per_unit, // 7000
            'minimum_charge_snapshot' => $this->serviceKiloanA->minimum_charge,
            'quantity_estimated' => 3.0,
            'line_estimated_amount' => 21000,
        ]);

        // 2. Update service price to Rp15.000/kg
        $updateRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
        ])->patchJson('/api/v1/store/services/' . $this->serviceKiloanA->id, [
            'price_per_unit' => 15000,
        ]);
        $updateRes->assertStatus(200);

        // 3. Verify order item snapshot remains intact at Rp7.000
        $freshOrderItem = OrderItem::find($orderItem->id);
        $this->assertEquals(7000, $freshOrderItem->unit_price_snapshot);
        $this->assertEquals(21000, $freshOrderItem->line_estimated_amount);
    }
}
