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

class PublicStoreListTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected ServiceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $storeAdminRole = Role::where('code', 'store_admin')->first();

        $this->owner = User::create([
            'name' => 'Owner Test',
            'email' => 'owner.test@example.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->owner->id]);
        UserRole::create([
            'user_id' => $this->owner->id,
            'role_id' => $storeAdminRole->id,
            'status' => 'active',
        ]);

        $this->category = ServiceCategory::create([
            'name' => 'Kiloan Reguler',
            'slug' => 'kiloan-reguler',
            'status' => 'active',
        ]);
    }

    /**
     * Test 1: Toko pending, rejected, suspended, and inactive do not appear in public list.
     */
    public function test_only_approved_and_active_stores_appear_in_public_list(): void
    {
        // 1. Approved & Active Store
        $activeStore = Store::create([
            'owner_user_id' => $this->owner->id,
            'name' => 'Laundry Approved Active',
            'address_text' => 'Jl. Ir. H. Juanda No. 10, Coblong, Kota Bandung, Jawa Barat 40132',
            'latitude' => -6.8852100,
            'longitude' => 107.6138200,
            'service_radius_km' => 5.0,
            'opening_hours' => [
                'senin' => '08:00 - 20:00',
                'selasa' => '08:00 - 20:00',
                'rabu' => '08:00 - 20:00',
                'kamis' => '08:00 - 20:00',
                'jumat' => '08:00 - 20:00',
                'sabtu' => '08:00 - 20:00',
                'minggu' => '08:00 - 20:00',
            ],
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        // 2. Pending Store
        Store::create([
            'owner_user_id' => $this->owner->id,
            'name' => 'Laundry Pending',
            'address_text' => 'Jl. Sukajadi No. 1, Kota Bandung',
            'approval_status' => 'pending',
            'operational_status' => 'active',
        ]);

        // 3. Rejected Store
        Store::create([
            'owner_user_id' => $this->owner->id,
            'name' => 'Laundry Rejected',
            'address_text' => 'Jl. Riau No. 2, Kota Bandung',
            'approval_status' => 'rejected',
            'operational_status' => 'active',
        ]);

        // 4. Suspended Store
        Store::create([
            'owner_user_id' => $this->owner->id,
            'name' => 'Laundry Suspended',
            'address_text' => 'Jl. Setiabudi No. 3, Kota Bandung',
            'approval_status' => 'approved',
            'operational_status' => 'suspended',
        ]);

        // 5. Inactive Store
        Store::create([
            'owner_user_id' => $this->owner->id,
            'name' => 'Laundry Inactive',
            'address_text' => 'Jl. Cihampelas No. 4, Kota Bandung',
            'approval_status' => 'approved',
            'operational_status' => 'inactive',
        ]);

        $response = $this->getJson('/api/v1/stores');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($activeStore->id, $data[0]['id']);
        $this->assertEquals('Laundry Approved Active', $data[0]['name']);
        $this->assertEquals('laundry-approved-active', $data[0]['slug']);
        $this->assertStringContainsString('Coblong, Kota Bandung', $data[0]['short_address']);
        $this->assertArrayHasKey('is_open_now', $data[0]);
        $this->assertArrayHasKey('opening_hours_today', $data[0]);
    }

    /**
     * Test 2: Starting price is calculated strictly from active services only.
     */
    public function test_starting_price_calculated_from_active_services_only(): void
    {
        $store = Store::create([
            'owner_user_id' => $this->owner->id,
            'name' => 'Laundry Best Price',
            'address_text' => 'Jl. Buah Batu No. 50, Lengkong, Kota Bandung',
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        // Inactive service with cheap price 3.000 (must be ignored)
        StoreService::create([
            'store_id' => $store->id,
            'category_id' => $this->category->id,
            'name' => 'Layanan Murah Nonaktif',
            'pricing_unit' => 'kg',
            'price_per_unit' => 3000,
            'minimum_charge' => 9000,
            'estimated_duration_hours' => 24,
            'is_active' => false,
        ]);

        // Active service 1: 10.000
        StoreService::create([
            'store_id' => $store->id,
            'category_id' => $this->category->id,
            'name' => 'Cuci Kering Setrika',
            'pricing_unit' => 'kg',
            'price_per_unit' => 10000,
            'minimum_charge' => 30000,
            'estimated_duration_hours' => 48,
            'is_active' => true,
        ]);

        // Active service 2: 7.000 (real minimum active price)
        StoreService::create([
            'store_id' => $store->id,
            'category_id' => $this->category->id,
            'name' => 'Cuci Lipat Hemat',
            'pricing_unit' => 'kg',
            'price_per_unit' => 7000,
            'minimum_charge' => 21000,
            'estimated_duration_hours' => 24,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/stores');
        $response->assertStatus(200);

        $storeData = $response->json('data.0');
        $this->assertEquals(7000, $storeData['starting_price']);
        $this->assertEquals(7000, $storeData['harga_mulai_dari']);
        $this->assertEquals(2, $storeData['active_services_count']);
        $this->assertCount(2, $storeData['top_services']);
        $this->assertContains('Cuci Lipat Hemat', $storeData['top_services']);
    }

    /**
     * Test 3: Distance is only returned when client coordinates (lat & lng) are provided.
     */
    public function test_distance_only_included_when_coordinates_provided(): void
    {
        Store::create([
            'owner_user_id' => $this->owner->id,
            'name' => 'Laundry Dago Atas',
            'address_text' => 'Jl. Ir. H. Juanda No. 100, Coblong, Kota Bandung',
            'latitude' => -6.8852100,
            'longitude' => 107.6138200,
            'service_radius_km' => 5.0,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        // 1. Without coordinates -> distance_km should NOT be provided
        $responseWithout = $this->getJson('/api/v1/stores');
        $responseWithout->assertStatus(200);
        $itemWithout = $responseWithout->json('data.0');
        $this->assertArrayNotHasKey('distance_km', $itemWithout);

        // 2. With client coordinates -> distance_km is calculated and included
        $responseWith = $this->getJson('/api/v1/stores?lat=-6.8860000&lng=107.6140000');
        $responseWith->assertStatus(200);
        $itemWith = $responseWith->json('data.0');
        $this->assertArrayHasKey('distance_km', $itemWith);
        $this->assertNotNull($itemWith['distance_km']);
        $this->assertLessThan(0.5, $itemWith['distance_km']);
        $this->assertTrue($itemWith['is_serviceable']);
    }

    /**
     * Test 4: Returns empty response when no approved and active stores exist.
     */
    public function test_empty_response_when_no_active_stores_exist(): void
    {
        // No stores created at all
        $response = $this->getJson('/api/v1/stores');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [],
            'meta' => [
                'total' => 0,
            ],
        ]);
        $this->assertEmpty($response->json('data'));
    }
}
