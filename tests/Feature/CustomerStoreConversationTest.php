<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerStoreConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_start_conversation_with_store(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $customerRole = Role::where('name', 'customer')->first();
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);
        if ($customerRole) {
            $customer->roles()->attach($customerRole);
        }

        $storeOwnerRole = Role::where('name', 'store_owner')->first();
        $storeOwner = User::factory()->create([
            'role' => User::ROLE_STORE_ADMIN,
            'is_active' => true,
        ]);
        if ($storeOwnerRole) {
            $storeOwner->roles()->attach($storeOwnerRole);
        }

        $store = Store::factory()->create([
            'owner_user_id' => $storeOwner->id,
            'name' => 'Laundry Berkah Jaya',
            'status' => 'approved',
            'is_active' => true,
        ]);

        Sanctum::actingAs($customer, ['role:customer']);

        $response = $this->postJson('/api/v1/conversations', [
            'store_id' => $store->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.store_id', (string) $store->id)
            ->assertJsonPath('data.customer_id', (string) $customer->id)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('conversations', [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'status' => 'active',
        ]);
    }
}
