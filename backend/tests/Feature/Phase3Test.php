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

class Phase3Test extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected string $customerToken;
    protected ServiceCategory $catKiloan;
    protected ServiceCategory $catSatuan;
    protected ServiceCategory $catInactive;
    protected Store $storeActive1;
    protected Store $storeActive2;
    protected Store $storePending;
    protected Store $storeInactive;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $customerRole = Role::where('code', 'customer')->first();
        $storeAdminRole = Role::where('code', 'store_admin')->first();

        // 1. Create standard verified Customer
        $this->customer = User::create([
            'name' => 'Budi Customer',
            'email' => 'budi.customer@example.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->customer->id]);
        UserRole::create([
            'user_id' => $this->customer->id,
            'role_id' => $customerRole->id,
            'status' => 'active',
        ]);
        $this->customerToken = $this->customer->createToken('auth_token')->plainTextToken;

        // 2. Create Service Categories
        $this->catKiloan = ServiceCategory::create([
            'name' => 'Kiloan Reguler',
            'slug' => 'kiloan-reguler',
            'status' => 'active',
        ]);

        $this->catSatuan = ServiceCategory::create([
            'name' => 'Cuci Satuan & Bedcover',
            'slug' => 'cuci-satuan',
            'status' => 'active',
        ]);

        $this->catInactive = ServiceCategory::create([
            'name' => 'Kategori Nonaktif',
            'slug' => 'kategori-nonaktif',
            'status' => 'inactive',
        ]);

        // 3. Create Store Owner
        $owner = User::create([
            'name' => 'Pak Joko Owner',
            'email' => 'joko.owner@example.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $owner->id]);
        UserRole::create([
            'user_id' => $owner->id,
            'role_id' => $storeAdminRole->id,
            'status' => 'active',
        ]);

        // Store 1: Dago Bandung (-6.8852100, 107.6138200), radius 5 km, approved & active
        $this->storeActive1 = Store::create([
            'owner_user_id' => $owner->id,
            'name' => 'Laundry Barokah Dago',
            'description' => 'Laundry kiloan dan dry clean higienis Dago',
            'address_text' => 'Jl. Ir. H. Juanda No. 120, Bandung',
            'latitude' => -6.8852100,
            'longitude' => 107.6138200,
            'service_radius_km' => 5.00,
            'opening_hours' => ['senin' => '08:00 - 20:00'],
            'approval_status' => 'approved',
            'operational_status' => 'active',
            'approved_at' => now(),
        ]);

        StoreService::create([
            'store_id' => $this->storeActive1->id,
            'category_id' => $this->catKiloan->id,
            'name' => 'Cuci Kering Setrika Kiloan',
            'pricing_unit' => 'kg',
            'price_per_unit' => 7000,
            'minimum_charge' => 21000,
            'estimated_duration_hours' => 48,
            'is_active' => true,
        ]);

        StoreService::create([
            'store_id' => $this->storeActive1->id,
            'category_id' => $this->catSatuan->id,
            'name' => 'Cuci Bedcover Jumbo',
            'pricing_unit' => 'item',
            'price_per_unit' => 25000,
            'minimum_charge' => 0,
            'estimated_duration_hours' => 48,
            'is_active' => true,
        ]);

        // Store 2: Dipatiukur Bandung (-6.8924300, 107.6162100), radius 4 km, approved & active
        $this->storeActive2 = Store::create([
            'owner_user_id' => $owner->id,
            'name' => 'Fresh & Clean Dipatiukur',
            'description' => 'Spesialis cuci express kiloan Dipatiukur',
            'address_text' => 'Jl. Dipati Ukur No. 45, Bandung',
            'latitude' => -6.8924300,
            'longitude' => 107.6162100,
            'service_radius_km' => 4.00,
            'opening_hours' => ['senin' => '07:30 - 21:00'],
            'approval_status' => 'approved',
            'operational_status' => 'active',
            'approved_at' => now(),
        ]);

        StoreService::create([
            'store_id' => $this->storeActive2->id,
            'category_id' => $this->catKiloan->id,
            'name' => 'Express 6 Jam Selesai',
            'pricing_unit' => 'kg',
            'price_per_unit' => 15000,
            'minimum_charge' => 30000,
            'estimated_duration_hours' => 6,
            'is_active' => true,
        ]);

        // Store 3: Pending Approval
        $this->storePending = Store::create([
            'owner_user_id' => $owner->id,
            'name' => 'Toko Laundry Baru Pending',
            'description' => 'Belum disetujui admin platform',
            'address_text' => 'Jl. Merdeka No. 1, Bandung',
            'latitude' => -6.9000000,
            'longitude' => 107.6100000,
            'service_radius_km' => 5.00,
            'approval_status' => 'pending',
            'operational_status' => 'inactive',
        ]);

        // Store 4: Approved but Inactive Operational Status
        $this->storeInactive = Store::create([
            'owner_user_id' => $owner->id,
            'name' => 'Toko Sedang Tutup Sementara',
            'description' => 'Tutup libur panjang',
            'address_text' => 'Jl. Riau No. 88, Bandung',
            'latitude' => -6.9050000,
            'longitude' => 107.6200000,
            'service_radius_km' => 5.00,
            'approval_status' => 'approved',
            'operational_status' => 'inactive',
        ]);
    }

    /**
     * Requirement 0: Pending role status should NOT be treated as active in authorization or GET /me
     */
    public function test_pending_roles_are_not_active_in_authorization_and_me_endpoint(): void
    {
        $courierRole = Role::where('code', 'courier')->first();
        $storeAdminRole = Role::where('code', 'store_admin')->first();

        // Assign pending courier & store_admin role to customer
        UserRole::create([
            'user_id' => $this->customer->id,
            'role_id' => $courierRole->id,
            'status' => 'pending',
        ]);
        UserRole::create([
            'user_id' => $this->customer->id,
            'role_id' => $storeAdminRole->id,
            'status' => 'pending',
        ]);

        $this->customer->refresh();

        // Model level checks
        $this->assertTrue($this->customer->hasRole('customer'));
        $this->assertFalse($this->customer->hasRole('courier'));
        $this->assertFalse($this->customer->hasRole('store_admin'));

        // API GET /me check
        $res = $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->getJson('/api/v1/me');

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'roles' => ['customer'],
                ],
            ]);

        $roles = $res->json('data.roles');
        $this->assertContains('customer', $roles);
        $this->assertNotContains('courier', $roles);
        $this->assertNotContains('store_admin', $roles);
    }

    /**
     * Requirement 4: GET /service-categories returns active categories only
     */
    public function test_can_list_active_service_categories(): void
    {
        $response = $this->getJson('/api/v1/service-categories');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        $slugs = collect($data)->pluck('slug')->all();

        $this->assertContains('kiloan-reguler', $slugs);
        $this->assertContains('cuci-satuan', $slugs);
        $this->assertNotContains('kategori-nonaktif', $slugs);
    }

    /**
     * Requirement 1: GET /stores lists approved & active stores with keyword and category filtering
     */
    public function test_can_list_approved_and_active_stores(): void
    {
        $response = $this->getJson('/api/v1/stores');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $names = collect($response->json('data'))->pluck('name')->all();

        $this->assertContains('Laundry Barokah Dago', $names);
        $this->assertContains('Fresh & Clean Dipatiukur', $names);
        $this->assertNotContains('Toko Laundry Baru Pending', $names);
        $this->assertNotContains('Toko Sedang Tutup Sementara', $names);
    }

    public function test_stores_can_be_filtered_by_keyword_search(): void
    {
        $response = $this->getJson('/api/v1/stores?search=Dago');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('Laundry Barokah Dago', $data[0]['name']);
    }

    public function test_stores_can_be_filtered_by_category_slug(): void
    {
        $response = $this->getJson('/api/v1/stores?category_slug=cuci-satuan');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('Laundry Barokah Dago', $data[0]['name']);
    }

    /**
     * Requirement 2: GET /stores/{id} returns store details and active services; 404 for pending/inactive
     */
    public function test_can_get_active_store_detail_with_services_and_hours(): void
    {
        $response = $this->getJson("/api/v1/stores/{$this->storeActive1->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->storeActive1->id,
                    'name' => 'Laundry Barokah Dago',
                    'service_radius_km' => 5.0,
                    'services_count' => 2,
                ],
            ]);

        $this->assertNotEmpty($response->json('data.services'));
        $this->assertNotEmpty($response->json('data.categories'));
    }

    public function test_store_detail_returns_404_for_pending_or_inactive_stores(): void
    {
        // Pending store returns 404
        $resPending = $this->getJson("/api/v1/stores/{$this->storePending->id}");
        $resPending->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                ],
            ]);

        // Inactive store returns 404
        $resInactive = $this->getJson("/api/v1/stores/{$this->storeInactive->id}");
        $resInactive->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                ],
            ]);

        // Non-existent UUID returns 404
        $resNotFound = $this->getJson('/api/v1/stores/00000000-0000-0000-0000-000000000000');
        $resNotFound->assertStatus(404);
    }

    /**
     * Requirement 3: GET /stores/nearby calculates Haversine distance, marks is_serviceable, caps radius at 10km
     */
    public function test_nearby_calculates_haversine_distance_and_serviceable_flag(): void
    {
        // Customer location: Near Simpang Dago (-6.8860000, 107.6140000)
        // Distance to Barokah Dago (-6.8852100, 107.6138200) is ~0.09 km (< 5km radius -> serviceable = true)
        // Distance to Dipatiukur (-6.8924300, 107.6162100) is ~0.75 km (< 4km radius -> serviceable = true)
        $response = $this->getJson('/api/v1/stores/nearby?lat=-6.8860000&lng=107.6140000&radius_km=10');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'total' => 2,
                    'serviceable_total' => 2,
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // Nearest should be Barokah Dago (~0.09km)
        $this->assertEquals('Laundry Barokah Dago', $data[0]['name']);
        $this->assertLessThan(0.5, $data[0]['distance_km']);
        $this->assertTrue($data[0]['is_serviceable']);

        // Second should be Fresh & Clean Dipatiukur (~0.75km)
        $this->assertEquals('Fresh & Clean Dipatiukur', $data[1]['name']);
        $this->assertLessThan(1.5, $data[1]['distance_km']);
        $this->assertTrue($data[1]['is_serviceable']);
    }

    public function test_nearby_marks_unserviceable_when_customer_is_outside_store_service_radius(): void
    {
        // Customer location: Kawasan Gede Bage Bandung (-6.9450000, 107.6800000)
        // Distance to Barokah Dago is ~9.8 km (> 5km service radius -> is_serviceable = false, but within 10km search)
        $response = $this->getJson('/api/v1/stores/nearby?lat=-6.9450000&lng=107.6800000&radius_km=10');

        $response->assertStatus(200);
        $data = $response->json('data');

        $barokah = collect($data)->firstWhere('name', 'Laundry Barokah Dago');
        if ($barokah) {
            $this->assertGreaterThan(5.0, $barokah['distance_km']);
            $this->assertFalse($barokah['is_serviceable']);
        }
    }

    public function test_nearby_handles_exact_same_coordinate_without_nan_or_error(): void
    {
        // Customer coordinate identical to Barokah Dago (-6.8852100, 107.6138200)
        $response = $this->getJson('/api/v1/stores/nearby?lat=-6.8852100&lng=107.6138200');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals('Laundry Barokah Dago', $data[0]['name']);
        $this->assertEquals(0.0, (float) $data[0]['distance_km']);
        $this->assertTrue($data[0]['is_serviceable']);
    }

    public function test_nearby_rejects_out_of_range_coordinates(): void
    {
        // Latitude out of range (> 90)
        $resLat = $this->getJson('/api/v1/stores/nearby?lat=95.0&lng=107.61');
        $resLat->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);

        // Longitude out of range (< -180)
        $resLng = $this->getJson('/api/v1/stores/nearby?lat=-6.88&lng=-185.0');
        $resLng->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);

        // Radius > 10 km rejected
        $resRad = $this->getJson('/api/v1/stores/nearby?lat=-6.88&lng=107.61&radius_km=15');
        $resRad->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_store_service_radius_cannot_exceed_10km_on_application(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->postJson('/api/v1/upgrades/store', [
                'name' => 'Toko Radius Terlalu Besar',
                'address_text' => 'Jl. Raya No. 123',
                'service_radius_km' => 15.0, // Exceeds 10km limit
                'document_type' => 'nib',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }
}
