<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressGeocodingTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $otherCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        $this->seed(SuperAdminSeeder::class);

        $this->customer = User::create([
            'name' => 'Budi Pelanggan',
            'email' => 'budi.customer@example.com',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        $this->otherCustomer = User::create([
            'name' => 'Siti Pelanggan',
            'email' => 'siti.customer@example.com',
            'phone' => '081298765432',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        Cache::flush();
    }

    public function test_authenticated_customer_can_reverse_geocode_coordinates_with_nominatim_fake(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'place_id' => 123456,
                'lat' => '-6.9175',
                'lon' => '107.6191',
                'display_name' => 'Jl. Merdeka No. 10, Babakan Ciamis, Sumur Bandung, Kota Bandung, Jawa Barat, 40117, Indonesia',
                'address' => [
                    'road' => 'Jl. Merdeka',
                    'house_number' => '10',
                    'suburb' => 'Babakan Ciamis',
                    'city_district' => 'Sumur Bandung',
                    'city' => 'Kota Bandung',
                    'state' => 'Jawa Barat',
                    'postcode' => '40117',
                    'country' => 'Indonesia',
                ],
            ], 200),
        ]);

        Sanctum::actingAs($this->customer);

        $response = $this->postJson('/api/v1/geocode/reverse', [
            'latitude' => -6.917464,
            'longitude' => 107.619123,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'latitude' => -6.917464,
                    'longitude' => 107.619123,
                    'road' => 'Jl. Merdeka No. 10',
                    'kelurahan' => 'Babakan Ciamis',
                    'kecamatan' => 'Sumur Bandung',
                    'city' => 'Kota Bandung',
                    'province' => 'Jawa Barat',
                    'postal_code' => '40117',
                ],
            ]);

        $data = $response->json('data');
        $this->assertStringContainsString('Jl. Merdeka No. 10', $data['formatted_address']);
        $this->assertStringContainsString('Kota Bandung', $data['formatted_address']);
    }

    public function test_reverse_geocode_validates_coordinate_boundaries_and_missing_params(): void
    {
        Sanctum::actingAs($this->customer);

        // Missing parameters
        $this->postJson('/api/v1/geocode/reverse', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);

        // Out of bounds latitude (> 90)
        $this->postJson('/api/v1/geocode/reverse', [
            'latitude' => 95.0,
            'longitude' => 107.0,
        ])->assertStatus(422);

        // Out of bounds longitude (> 180)
        $this->postJson('/api/v1/geocode/reverse', [
            'latitude' => -6.0,
            'longitude' => 185.0,
        ])->assertStatus(422);
    }

    public function test_reverse_geocode_caches_results_by_rounded_coordinates(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'Jl. Asia Afrika No. 5, Braga, Sumur Bandung, Kota Bandung, Jawa Barat',
                'address' => [
                    'road' => 'Jl. Asia Afrika',
                    'house_number' => '5',
                    'suburb' => 'Braga',
                    'city_district' => 'Sumur Bandung',
                    'city' => 'Kota Bandung',
                    'state' => 'Jawa Barat',
                    'postcode' => '40111',
                ],
            ], 200),
        ]);

        Sanctum::actingAs($this->customer);

        // Request 1
        $r1 = $this->postJson('/api/v1/geocode/reverse', [
            'latitude' => -6.920412,
            'longitude' => 107.608314,
        ]);
        $r1->assertStatus(200);

        // Request 2 with slight jitter that rounds to same 4 decimals (-6.9204, 107.6083)
        $r2 = $this->postJson('/api/v1/geocode/reverse', [
            'latitude' => -6.920448,
            'longitude' => 107.608331,
        ]);
        $r2->assertStatus(200);

        // Nominatim should only be called once because the second request hits cache
        Http::assertSentCount(1);
    }

    public function test_reverse_geocode_gracefully_handles_nominatim_failure(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response('Server Error', 500),
        ]);

        Sanctum::actingAs($this->customer);

        $response = $this->postJson('/api/v1/geocode/reverse', [
            'latitude' => -6.9175,
            'longitude' => 107.6191,
        ]);

        $response->assertStatus(502)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'GEOCODE_FAILED',
                ],
            ]);
    }

    public function test_customer_can_create_address_with_coordinates_and_structured_fields(): void
    {
        Sanctum::actingAs($this->customer);

        $response = $this->postJson('/api/v1/addresses', [
            'label' => 'Rumah Utama',
            'address' => 'Jl. Riau No. 45',
            'latitude' => -6.908512,
            'longitude' => 107.619845,
            'city' => 'Kota Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40115',
            'kelurahan' => 'Citarum',
            'kecamatan' => 'Bandung Wetan',
            'address_note' => 'Pagar putih sebelah minimarket',
            'is_default' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'label' => 'Rumah Utama',
                    'city' => 'Kota Bandung',
                    'province' => 'Jawa Barat',
                    'postal_code' => '40115',
                    'kelurahan' => 'Citarum',
                    'kecamatan' => 'Bandung Wetan',
                    'address_note' => 'Pagar putih sebelah minimarket',
                    'is_default' => true,
                ],
            ]);

        $this->assertDatabaseHas('customer_addresses', [
            'user_id' => $this->customer->id,
            'label' => 'Rumah Utama',
            'city' => 'Kota Bandung',
            'postal_code' => '40115',
            'is_default' => 1,
        ]);
    }

    public function test_address_isolation_prevents_unauthorized_access_or_modification(): void
    {
        // Customer A creates address
        Sanctum::actingAs($this->customer);
        $addrA = Address::create([
            'user_id' => $this->customer->id,
            'label' => 'Rumah Budi',
            'recipient_name' => $this->customer->name,
            'phone' => '081234567890',
            'address' => 'Jl. Sukajadi No. 12',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40161',
            'latitude' => -6.8875,
            'longitude' => 107.5975,
            'is_default' => true,
        ]);

        // Customer B cannot view Customer A's address in their address list
        Sanctum::actingAs($this->otherCustomer);
        $listResponse = $this->getJson('/api/v1/addresses');
        $listResponse->assertStatus(200);
        $addrIds = collect($listResponse->json('data'))->pluck('id')->toArray();
        $this->assertNotContains((string) $addrA->id, $addrIds);

        // Customer B cannot update Customer A's address
        $updateResponse = $this->putJson("/api/v1/addresses/{$addrA->id}", [
            'label' => 'Hacked Label',
        ]);
        $updateResponse->assertStatus(403);

        // Customer B cannot delete Customer A's address
        $deleteResponse = $this->deleteJson("/api/v1/addresses/{$addrA->id}");
        $deleteResponse->assertStatus(403);

        // Customer B cannot set Customer A's address as default
        $setDefaultResponse = $this->postJson("/api/v1/addresses/{$addrA->id}/set-default");
        $setDefaultResponse->assertStatus(403);

        // Ensure database record is untouched
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $addrA->id,
            'label' => 'Rumah Budi',
        ]);
    }
}
