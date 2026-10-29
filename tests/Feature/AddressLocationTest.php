<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressLocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_customer_can_create_address_and_get_coordinates(): void
    {
        $customerRole = Role::where('name', 'customer')->first();
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);
        if ($customerRole) {
            $customer->roles()->attach($customerRole);
        }

        Sanctum::actingAs($customer, ['role:customer']);

        $response = $this->postJson('/api/v1/addresses', [
            'label' => 'Rumah',
            'address' => 'Jl. Kesambi No. 10, Cirebon',
            'city' => 'Cirebon',
            'province' => 'Jawa Barat',
            'latitude' => -6.7210,
            'longitude' => 108.5530,
            'is_default' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.latitude', -6.7210)
            ->assertJsonPath('data.longitude', 108.5530)
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.city', 'Cirebon');

        $this->assertDatabaseHas('customer_addresses', [
            'user_id' => $customer->id,
            'city' => 'Cirebon',
            'is_default' => true,
        ]);

        $listResponse = $this->getJson('/api/v1/addresses');
        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.city', 'Cirebon')
            ->assertJsonPath('data.0.latitude', -6.7210)
            ->assertJsonPath('data.0.longitude', 108.5530);
    }

    public function test_customer_address_without_coords_is_auto_geocoded(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'lat' => '-6.7392743',
                    'lon' => '108.5238416',
                    'display_name' => 'Jalan Perjuangan, Cirebon, Jawa Barat',
                ],
            ], 200),
        ]);

        $customerRole = Role::where('name', 'customer')->first();
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);
        if ($customerRole) {
            $customer->roles()->attach($customerRole);
        }

        Sanctum::actingAs($customer, ['role:customer']);

        $response = $this->postJson('/api/v1/addresses', [
            'label' => 'Rumah Cirebon',
            'address' => 'Jl. Perjuangan 10b, Majasem Cirebon',
            'city' => 'Cirebon',
            'province' => 'Jawa Barat',
            'is_default' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.latitude', -6.7392743)
            ->assertJsonPath('data.longitude', 108.5238416);

        $this->assertDatabaseHas('customer_addresses', [
            'user_id' => $customer->id,
            'label' => 'Rumah Cirebon',
            'is_default' => true,
        ]);
    }

    public function test_setting_address_as_default_updates_status(): void
    {
        $customerRole = Role::where('name', 'customer')->first();
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);
        if ($customerRole) {
            $customer->roles()->attach($customerRole);
        }

        $addr1 = Address::create([
            'user_id' => $customer->id,
            'label' => 'Rumah Bandung',
            'recipient_name' => $customer->name,
            'phone' => '081234567890',
            'address' => 'Jl. Dago No. 1, Bandung',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40115',
            'latitude' => -6.8900,
            'longitude' => 107.6150,
            'is_default' => true,
        ]);

        $addr2 = Address::create([
            'user_id' => $customer->id,
            'label' => 'Rumah Cirebon',
            'recipient_name' => $customer->name,
            'phone' => '081234567890',
            'address' => 'Jl. Perjuangan 10b, Cirebon',
            'city' => 'Cirebon',
            'province' => 'Jawa Barat',
            'postal_code' => '45135',
            'latitude' => -6.7392,
            'longitude' => 108.5238,
            'is_default' => false,
        ]);

        Sanctum::actingAs($customer, ['role:customer']);

        $response = $this->postJson("/api/v1/addresses/{$addr2->id}/set-default");
        $response->assertStatus(200)
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.city', 'Cirebon');

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $addr1->id,
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $addr2->id,
            'is_default' => true,
        ]);
    }
}
