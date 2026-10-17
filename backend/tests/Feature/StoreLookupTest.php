<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StoreLookupTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'code' => 'customer'], ['name' => 'Customer']);
        Role::firstOrCreate(['id' => 2, 'code' => 'store_admin'], ['name' => 'Store Admin']);

        $this->owner = User::create([
            'name' => 'Store Owner',
            'email' => 'owner@laundryku.test',
            'phone' => '+6281112223334',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'verified_at' => now(),
        ]);

        UserRole::create([
            'user_id' => $this->owner->id,
            'role_id' => 2,
            'status' => 'active',
        ]);
    }

    public function test_lookup_matches_store_name_partially_and_case_insensitively(): void
    {
        Store::create([
            'owner_user_id' => $this->owner->id,
            'name' => 'Berkah Express Buah Batu',
            'description' => 'Laundry express berkualitas',
            'address_text' => 'Jl. Buah Batu No. 123, Bandung',
            'latitude' => -6.9300,
            'longitude' => 107.6200,
            'service_radius_km' => 5.0,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        // 1. Lowercase search "buah"
        $res1 = $this->getJson('/api/v1/stores/lookup?q=buah');
        $res1->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Berkah Express Buah Batu');

        // 2. Uppercase search "BERKAH"
        $res2 = $this->getJson('/api/v1/stores/lookup?q=BERKAH');
        $res2->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_lookup_excludes_pending_rejected_or_inactive_stores(): void
    {
        // Approved & Active (Should appear)
        Store::create([
            'owner_user_id' => $this->owner->id,
            'name' => 'Active Super Laundry',
            'address_text' => 'Jl. Merdeka No. 1, Bandung',
            'latitude' => -6.9100,
            'longitude' => 107.6100,
            'service_radius_km' => 5.0,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        // Pending Approval (Must NOT appear)
        Store::create([
            'owner_user_id' => $this->owner->id,
            'name' => 'Pending Super Laundry',
            'address_text' => 'Jl. Merdeka No. 2, Bandung',
            'latitude' => -6.9100,
            'longitude' => 107.6100,
            'service_radius_km' => 5.0,
            'approval_status' => 'pending',
            'operational_status' => 'active',
        ]);

        // Rejected (Must NOT appear)
        Store::create([
            'owner_user_id' => $this->owner->id,
            'name' => 'Rejected Super Laundry',
            'address_text' => 'Jl. Merdeka No. 3, Bandung',
            'latitude' => -6.9100,
            'longitude' => 107.6100,
            'service_radius_km' => 5.0,
            'approval_status' => 'rejected',
            'operational_status' => 'active',
        ]);

        // Inactive / Suspended (Must NOT appear)
        Store::create([
            'owner_user_id' => $this->owner->id,
            'name' => 'Suspended Super Laundry',
            'address_text' => 'Jl. Merdeka No. 4, Bandung',
            'latitude' => -6.9100,
            'longitude' => 107.6100,
            'service_radius_km' => 5.0,
            'approval_status' => 'approved',
            'operational_status' => 'suspended',
        ]);

        $response = $this->getJson('/api/v1/stores/lookup?q=Super');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Active Super Laundry', $data[0]['name']);
    }

    public function test_lookup_returns_empty_when_query_too_short(): void
    {
        $response = $this->getJson('/api/v1/stores/lookup?q=a');
        $response->assertStatus(200)
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_lookup_limits_to_maximum_10_results(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            Store::create([
                'owner_user_id' => $this->owner->id,
                'name' => sprintf('Outlet Ceria Cabang %02d', $i),
                'address_text' => 'Jl. Ceria No. ' . $i,
                'latitude' => -6.9000,
                'longitude' => 107.6000,
                'service_radius_km' => 5.0,
                'approval_status' => 'approved',
                'operational_status' => 'active',
            ]);
        }

        $response = $this->getJson('/api/v1/stores/lookup?q=Ceria');
        $response->assertStatus(200)
            ->assertJsonCount(10, 'data');
    }

    public function test_lookup_does_not_leak_sensitive_fields(): void
    {
        $store = Store::create([
            'owner_user_id' => $this->owner->id,
            'name' => 'Clean Safe Laundry',
            'description' => 'Aman dan bersih',
            'address_text' => 'Jl. Aman No. 10',
            'latitude' => -6.9000,
            'longitude' => 107.6000,
            'service_radius_km' => 5.0,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/stores/lookup?q=Safe');
        $response->assertStatus(200);

        $first = $response->json('data.0');
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('address_text', $first);
        $this->assertArrayNotHasKey('owner_user_id', $first);
        $this->assertArrayNotHasKey('bank_account', $first);
    }
}
