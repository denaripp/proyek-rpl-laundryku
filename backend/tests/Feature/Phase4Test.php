<?php

namespace Tests\Feature;

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

class Phase4Test extends TestCase
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

    protected StoreService $serviceKiloanA;
    protected StoreService $serviceSatuanA;
    protected StoreService $serviceInactiveA;

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

        // 2. Store Owner A (Laundry Barokah)
        $this->storeOwnerA = User::create([
            'name' => 'Pak Joko A',
            'email' => 'joko.a@example.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->storeOwnerA->id]);
        UserRole::create(['user_id' => $this->storeOwnerA->id, 'role_id' => $customerRole->id, 'status' => 'active']);
        $this->tokenA = $this->storeOwnerA->createToken('auth_token')->plainTextToken;

        $this->storeA = Store::create([
            'owner_user_id' => $this->storeOwnerA->id,
            'name' => 'Laundry Barokah A',
            'address_text' => 'Jl. Dago No. 10, Bandung',
            'latitude' => -6.885,
            'longitude' => 107.613,
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
            'pricing_unit' => 'kg',
            'price_per_unit' => 7000,
            'minimum_charge' => 21000, // min charge Rp21.000 (equiv 3kg)
            'estimated_duration_hours' => 48,
            'is_active' => true,
        ]);

        $this->serviceSatuanA = StoreService::create([
            'store_id' => $this->storeA->id,
            'category_id' => $this->catSatuan->id,
            'name' => 'Cuci Bedcover Satuan',
            'pricing_unit' => 'item',
            'price_per_unit' => 25000,
            'minimum_charge' => 0,
            'estimated_duration_hours' => 24,
            'is_active' => true,
        ]);

        $this->serviceInactiveA = StoreService::create([
            'store_id' => $this->storeA->id,
            'category_id' => $this->catSatuan->id,
            'name' => 'Layanan Sedang Tutup',
            'pricing_unit' => 'item',
            'price_per_unit' => 50000,
            'minimum_charge' => 0,
            'estimated_duration_hours' => 24,
            'is_active' => false,
        ]);

        // 3. Store Owner B (Fresh & Clean)
        $this->storeOwnerB = User::create([
            'name' => 'Ibu Maya B',
            'email' => 'maya.b@example.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->storeOwnerB->id]);
        UserRole::create(['user_id' => $this->storeOwnerB->id, 'role_id' => $customerRole->id, 'status' => 'active']);
        $this->tokenB = $this->storeOwnerB->createToken('auth_token')->plainTextToken;

        $this->storeB = Store::create([
            'owner_user_id' => $this->storeOwnerB->id,
            'name' => 'Fresh & Clean B',
            'address_text' => 'Jl. Dipatiukur No. 20, Bandung',
            'latitude' => -6.892,
            'longitude' => 107.616,
            'service_radius_km' => 4.0,
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

        // 4. Regular Customer
        $this->customer = User::create([
            'name' => 'Budi Customer',
            'email' => 'budi.customer@example.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->customer->id]);
        UserRole::create(['user_id' => $this->customer->id, 'role_id' => $customerRole->id, 'status' => 'active']);
        $this->customerToken = $this->customer->createToken('auth_token')->plainTextToken;
    }

    /**
     * Store admin can list their store services.
     */
    public function test_store_admin_can_list_own_services(): void
    {
        $res = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->getJson('/api/v1/store-admin/services');

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'store_id' => $this->storeA->id,
                    'total' => 3,
                ],
            ]);

        $names = collect($res->json('data'))->pluck('name')->all();
        $this->assertContains('Cuci Kering Setrika Kiloan', $names);
        $this->assertContains('Cuci Bedcover Satuan', $names);
    }

    /**
     * Store admin can create a new service.
     */
    public function test_store_admin_can_create_new_service(): void
    {
        $res = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->postJson('/api/v1/store-admin/services', [
                'store_id' => $this->storeA->id,
                'category_id' => $this->catKiloan->id,
                'name' => 'Express 8 Jam Selesai',
                'pricing_unit' => 'kg',
                'price_per_unit' => 12000,
                'minimum_charge' => 36000,
                'estimated_duration_hours' => 8,
                'is_active' => true,
            ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Express 8 Jam Selesai',
                    'pricing_unit' => 'kg',
                    'price_per_unit' => 12000,
                    'minimum_charge' => 36000,
                    'estimated_duration_hours' => 8,
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('store_services', [
            'store_id' => $this->storeA->id,
            'name' => 'Express 8 Jam Selesai',
            'price_per_unit' => 12000,
        ]);
    }

    /**
     * Store admin cannot create service for another store they do not own.
     */
    public function test_store_admin_cannot_create_service_for_another_store(): void
    {
        $res = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->postJson('/api/v1/store-admin/services', [
                'store_id' => $this->storeB->id, // Store owned by Owner B
                'category_id' => $this->catKiloan->id,
                'name' => 'Hacker Express Service',
                'pricing_unit' => 'kg',
                'price_per_unit' => 1000,
                'estimated_duration_hours' => 1,
            ]);

        $res->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                ],
            ]);
    }

    /**
     * Store admin can update their own service.
     */
    public function test_store_admin_can_update_own_service(): void
    {
        $res = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->putJson("/api/v1/store-admin/services/{$this->serviceKiloanA->id}", [
                'price_per_unit' => 8000,
                'minimum_charge' => 24000,
                'estimated_duration_hours' => 36,
            ]);

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->serviceKiloanA->id,
                    'price_per_unit' => 8000,
                    'minimum_charge' => 24000,
                    'estimated_duration_hours' => 36,
                ],
            ]);

        $this->serviceKiloanA->refresh();
        $this->assertEquals(8000, $this->serviceKiloanA->price_per_unit);
        $this->assertEquals(24000, $this->serviceKiloanA->minimum_charge);
    }

    /**
     * Store admin cannot update service belonging to another store.
     */
    public function test_store_admin_cannot_update_another_store_service(): void
    {
        $res = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->putJson("/api/v1/store-admin/services/{$this->serviceKiloanA->id}", [
                'price_per_unit' => 500,
            ]);

        $res->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                ],
            ]);
    }

    /**
     * Store admin can toggle service status (active/inactive).
     */
    public function test_store_admin_can_toggle_service_status(): void
    {
        $res = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->patchJson("/api/v1/store-admin/services/{$this->serviceKiloanA->id}/toggle-status");

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->serviceKiloanA->id,
                    'is_active' => false,
                ],
            ]);

        $this->serviceKiloanA->refresh();
        $this->assertFalse($this->serviceKiloanA->is_active);

        // Toggle back to active
        $res2 = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->patchJson("/api/v1/store-admin/services/{$this->serviceKiloanA->id}/toggle-status");

        $res2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_active' => true,
                ],
            ]);
    }

    /**
     * Store admin can delete their own service.
     */
    public function test_store_admin_can_delete_own_service(): void
    {
        $res = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->deleteJson("/api/v1/store-admin/services/{$this->serviceSatuanA->id}");

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'deleted' => true,
                ],
            ]);

        $this->assertDatabaseMissing('store_services', ['id' => $this->serviceSatuanA->id]);
    }

    /**
     * Price estimation endpoint calculates subtotal accurately.
     */
    public function test_price_estimation_calculates_subtotal_accurately(): void
    {
        // Service Kiloan: 7000/kg, min charge 21000
        // Service Satuan: 25000/item, min charge 0
        // Input: 4.5 kg Kiloan (4.5 * 7000 = 31500 > 21000 min charge) + 2 items Bedcover (2 * 25000 = 50000)
        // Total expected: 31500 + 50000 = 81500
        $res = $this->postJson('/api/v1/pricing/estimate', [
            'store_id' => $this->storeA->id,
            'items' => [
                [
                    'service_id' => $this->serviceKiloanA->id,
                    'quantity' => 4.5,
                ],
                [
                    'service_id' => $this->serviceSatuanA->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'store' => [
                        'id' => $this->storeA->id,
                    ],
                    'items' => [
                        [
                            'service_id' => $this->serviceKiloanA->id,
                            'quantity' => 4.5,
                            'subtotal' => 31500,
                            'minimum_charge_applied' => false,
                            'charged_amount' => 31500,
                        ],
                        [
                            'service_id' => $this->serviceSatuanA->id,
                            'quantity' => 2,
                            'subtotal' => 50000,
                            'minimum_charge_applied' => false,
                            'charged_amount' => 50000,
                        ],
                    ],
                    'summary' => [
                        'total_items' => 2,
                        'total_raw_subtotal' => 81500,
                        'total_estimated_price' => 81500,
                        'max_estimated_duration_hours' => 48,
                    ],
                ],
            ]);
    }

    /**
     * Price estimation enforces minimum_charge when subtotal is below minimum charge.
     */
    public function test_price_estimation_enforces_minimum_charge(): void
    {
        // Service Kiloan: 7000/kg, min charge 21000
        // Input: 1.5 kg (1.5 * 7000 = 10500 < 21000 min charge)
        // Expected: minimum_charge_applied = true, charged_amount = 21000
        $res = $this->postJson('/api/v1/pricing/estimate', [
            'store_id' => $this->storeA->id,
            'items' => [
                [
                    'service_id' => $this->serviceKiloanA->id,
                    'quantity' => 1.5,
                ],
            ],
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'items' => [
                        [
                            'service_id' => $this->serviceKiloanA->id,
                            'quantity' => 1.5,
                            'subtotal' => 10500,
                            'minimum_charge' => 21000,
                            'minimum_charge_applied' => true,
                            'charged_amount' => 21000,
                        ],
                    ],
                    'summary' => [
                        'total_raw_subtotal' => 10500,
                        'total_estimated_price' => 21000,
                    ],
                ],
            ]);
    }

    /**
     * Price estimation rejects inactive service.
     */
    public function test_price_estimation_rejects_inactive_service(): void
    {
        $res = $this->postJson('/api/v1/pricing/estimate', [
            'store_id' => $this->storeA->id,
            'items' => [
                [
                    'service_id' => $this->serviceInactiveA->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $res->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'SERVICE_INACTIVE',
                ],
            ]);
    }

    /**
     * Price estimation rejects service that does not belong to the selected store.
     */
    public function test_price_estimation_rejects_service_from_different_store(): void
    {
        // Create service for Store B
        $serviceB = StoreService::create([
            'store_id' => $this->storeB->id,
            'category_id' => $this->catKiloan->id,
            'name' => 'Service Store B',
            'pricing_unit' => 'kg',
            'price_per_unit' => 10000,
            'estimated_duration_hours' => 24,
            'is_active' => true,
        ]);

        // Attempt estimating for Store A using service from Store B
        $res = $this->postJson('/api/v1/pricing/estimate', [
            'store_id' => $this->storeA->id,
            'items' => [
                [
                    'service_id' => $serviceB->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $res->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'SERVICE_NOT_FOUND_IN_STORE',
                ],
            ]);
    }
}
