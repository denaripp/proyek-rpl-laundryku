<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreLocationCoordinatesAndRadiusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SuperAdminSeeder::class);
    }

    protected function createCustomer(string $email = 'customer@example.com'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'role' => User::ROLE_CUSTOMER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $role = Role::where('name', 'customer')->first();
        if ($role) {
            $user->roles()->attach($role->id, ['assigned_at' => now()]);
        }

        return $user;
    }

    protected function createStoreAdmin(string $email = 'mitra@example.com'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'role' => User::ROLE_STORE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $role = Role::where('name', 'store_admin')->first() ?? Role::where('name', 'store_owner')->first();
        if ($role) {
            $user->roles()->attach($role->id, ['assigned_at' => now()]);
        }

        return $user;
    }

    /**
     * Requirement: Mitra registration requires valid coordinates, rejecting empty and default (-6.2088, 106.8456).
     */
    public function test_mitra_registration_requires_coordinates_and_rejects_empty_or_default_coords(): void
    {
        $customer = $this->createCustomer('cust_reg@example.com');
        Sanctum::actingAs($customer, ['role:customer']);

        $basePayload = [
            'name' => 'Calon Mitra Laundry',
            'email' => 'calon_mitra_' . uniqid() . '@example.com',
            'phone' => '0855' . rand(10000000, 99999999),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'store_name' => 'Laundry Tes Lokasi',
            'store_address' => 'Jl. Pemuda No. 10',
            'city' => 'Kota Cirebon',
            'province' => 'Jawa Barat',
        ];

        // 1. Rejected if coordinates are null
        $resNull = $this->postJson('/api/v1/auth/mitra/register', array_merge($basePayload, [
            'latitude' => null,
            'longitude' => null,
        ]));
        $resNull->assertStatus(422);
        $resNull->assertJsonValidationErrors(['latitude']);

        // 2. Rejected if coordinates are (0, 0)
        $resZero = $this->postJson('/api/v1/auth/mitra/register', array_merge($basePayload, [
            'latitude' => 0.0,
            'longitude' => 0.0,
        ]));
        $resZero->assertStatus(422);
        $resZero->assertJsonValidationErrors(['latitude']);

        // 3. Rejected if coordinates are default (-6.2088, 106.8456)
        $resDefault = $this->postJson('/api/v1/auth/mitra/register', array_merge($basePayload, [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
        ]));
        $resDefault->assertStatus(422);
        $resDefault->assertJsonValidationErrors(['latitude']);

        // 4. Success if coordinates are valid non-default (e.g. Cirebon)
        $resSuccess = $this->postJson('/api/v1/auth/mitra/register', array_merge($basePayload, [
            'latitude' => -6.7372,
            'longitude' => 108.5507,
        ]));
        $resSuccess->assertStatus(201);
        $this->assertEquals(-6.7372, (float) $resSuccess->json('store.latitude'));
        $this->assertEquals(108.5507, (float) $resSuccess->json('store.longitude'));
    }

    /**
     * Requirement: Store settings update requires valid coordinates, rejecting empty or default coords.
     */
    public function test_store_settings_update_requires_coordinates_and_rejects_empty_or_default_coords(): void
    {
        $owner = $this->createStoreAdmin('owner_cirebon@laundry.id');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Laundry Awal',
            'phone' => '081234567890',
            'address' => 'Jl. Cipto No. 1',
            'city' => 'Kota Cirebon',
            'province' => 'Jawa Barat',
            'latitude' => -6.7372,
            'longitude' => 108.5507,
            'service_radius_km' => 5.0,
            'status' => 'approved',
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner, ['role:store_admin']);

        // 1. Rejected if latitude is null
        $resNull = $this->patchJson('/api/v1/mitra/store', [
            'name' => 'Laundry Diubah',
            'phone' => '081234567890',
            'address' => 'Jl. Cipto No. 1',
            'latitude' => null,
            'longitude' => null,
        ]);
        $resNull->assertStatus(422);
        $resNull->assertJsonValidationErrors(['latitude']);

        // 2. Rejected if coordinates are (0, 0)
        $resZero = $this->patchJson('/api/v1/mitra/store', [
            'name' => 'Laundry Diubah',
            'phone' => '081234567890',
            'address' => 'Jl. Cipto No. 1',
            'latitude' => 0.0,
            'longitude' => 0.0,
        ]);
        $resZero->assertStatus(422);
        $resZero->assertJsonValidationErrors(['latitude']);

        // 3. Rejected if coordinates are default (-6.2088, 106.8456)
        $resDefault = $this->patchJson('/api/v1/mitra/store', [
            'name' => 'Laundry Diubah',
            'phone' => '081234567890',
            'address' => 'Jl. Cipto No. 1',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
        ]);
        $resDefault->assertStatus(422);
        $resDefault->assertJsonValidationErrors(['latitude']);

        // 4. Accepted when valid coordinates are provided
        $resSuccess = $this->patchJson('/api/v1/mitra/store', [
            'name' => 'Laundry Cirebon Terupdate',
            'phone' => '081234567890',
            'address' => 'Jl. Kartini No. 55',
            'latitude' => -6.7214,
            'longitude' => 108.5583,
            'service_radius_km' => 7.5,
        ]);
        $resSuccess->assertStatus(200);
        $store->refresh();
        $this->assertEquals(-6.7214, (float) $store->latitude);
        $this->assertEquals(108.5583, (float) $store->longitude);
    }

    /**
     * Requirement: Cirebon store appears for Cirebon customer, but NOT for Bandung customer outside radius.
     */
    public function test_cirebon_store_appears_for_cirebon_customer_but_not_for_bandung_customer_outside_radius(): void
    {
        // 1. Create Cirebon Store (lat: -6.7372, lng: 108.5507, radius: 10 km)
        $cirebonOwner = $this->createStoreAdmin('owner_cirebon_test@laundry.id');
        $cirebonStore = Store::factory()->create([
            'owner_user_id' => $cirebonOwner->id,
            'name' => 'Laundry Berkah Cirebon',
            'slug' => 'laundry-berkah-cirebon',
            'phone' => '08111222333',
            'address' => 'Jl. Siliwangi No. 10',
            'city' => 'Kota Cirebon',
            'province' => 'Jawa Barat',
            'latitude' => -6.7372000,
            'longitude' => 108.5507000,
            'service_radius_km' => 10.0,
            'status' => 'approved',
            'is_active' => true,
        ]);

        // 2. Create Bandung Store (lat: -6.9147, lng: 107.6098, radius: 10 km)
        $bandungOwner = $this->createStoreAdmin('owner_bandung_test@laundry.id');
        $bandungStore = Store::factory()->create([
            'owner_user_id' => $bandungOwner->id,
            'name' => 'Laundry Dago Bandung',
            'slug' => 'laundry-dago-bandung',
            'phone' => '08222333444',
            'address' => 'Jl. Ir. H. Juanda No. 20',
            'city' => 'Kota Bandung',
            'province' => 'Jawa Barat',
            'latitude' => -6.9147000,
            'longitude' => 107.6098000,
            'service_radius_km' => 10.0,
            'status' => 'approved',
            'is_active' => true,
        ]);

        // Add active services to both stores
        $service = Service::firstOrCreate(
            ['name' => 'Cuci Komplit'],
            ['is_active' => true]
        );

        ServicePrice::create([
            'store_id' => $cirebonStore->id,
            'service_id' => $service->id,
            'price_per_unit' => 7000,
            'pricing_unit' => 'kg',
            'is_active' => true,
        ]);

        ServicePrice::create([
            'store_id' => $bandungStore->id,
            'service_id' => $service->id,
            'price_per_unit' => 8000,
            'pricing_unit' => 'kg',
            'is_active' => true,
        ]);

        // Scenario A: Customer in Cirebon (-6.7380, 108.5510) searches nearby stores within 10 km
        $resCirebon = $this->getJson('/api/v1/stores/nearby?lat=-6.7380000&lng=108.5510000&radius_km=10');
        $resCirebon->assertStatus(200);

        $cirebonStoreNames = collect($resCirebon->json('data'))->pluck('name')->toArray();

        // Cirebon store MUST appear
        $this->assertContains('Laundry Berkah Cirebon', $cirebonStoreNames);
        // Bandung store MUST NOT appear (distance is ~130 km, well outside 10 km)
        $this->assertNotContains('Laundry Dago Bandung', $cirebonStoreNames);

        // Scenario B: Customer in Bandung (-6.9150, 107.6100) searches nearby stores within 10 km
        $resBandung = $this->getJson('/api/v1/stores/nearby?lat=-6.9150000&lng=107.6100000&radius_km=10');
        $resBandung->assertStatus(200);

        $bandungStoreNames = collect($resBandung->json('data'))->pluck('name')->toArray();

        // Bandung store MUST appear
        $this->assertContains('Laundry Dago Bandung', $bandungStoreNames);
        // Cirebon store MUST NOT appear for Bandung customer
        $this->assertNotContains('Laundry Berkah Cirebon', $bandungStoreNames);
    }
}
