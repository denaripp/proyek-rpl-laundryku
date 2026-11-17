<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreOrdersTabCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_orders_endpoint_returns_tab_counts_matching_dashboard_categories(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $owner = User::factory()->create(['role' => 'store_admin', 'is_active' => true]);
        $roleStore = \App\Models\Role::where('name', 'store_owner')->first();
        if ($roleStore) {
            $owner->roles()->attach($roleStore);
        }
        $owner->refresh();
        $store = Store::factory()->create(['owner_user_id' => $owner->id, 'is_active' => true]);

        // Seed 1 created (pending pickup)
        Order::factory()->create(['store_id' => $store->id, 'status' => 'created']);

        // Seed 1 at_store, 1 weighed (need weigh)
        Order::factory()->create(['store_id' => $store->id, 'status' => 'at_store']);
        Order::factory()->create(['store_id' => $store->id, 'status' => 'weighed']);

        // Seed 1 paid, 1 processing (processing)
        Order::factory()->create(['store_id' => $store->id, 'status' => 'paid']);
        Order::factory()->create(['store_id' => $store->id, 'status' => 'processing']);

        // Seed 1 ready_for_delivery, 1 courier_assigned_for_delivery (delivery)
        Order::factory()->create(['store_id' => $store->id, 'status' => 'ready_for_delivery']);
        Order::factory()->create(['store_id' => $store->id, 'status' => 'courier_assigned_for_delivery']);

        // Seed 1 completed
        Order::factory()->create(['store_id' => $store->id, 'status' => 'completed']);

        $response = $this->actingAs($owner)->getJson("/api/v1/orders?role=store_admin&per_page=100");

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data',
            'tab_counts' => [
                'all',
                'pending_pickup',
                'need_weigh',
                'processing',
                'delivery',
                'completed',
            ],
        ]);

        $tabs = $response->json('tab_counts');
        $this->assertEquals(8, $tabs['all']);
        $this->assertEquals(1, $tabs['pending_pickup']);
        $this->assertEquals(2, $tabs['need_weigh']);
        $this->assertEquals(2, $tabs['processing']);
        $this->assertEquals(2, $tabs['delivery']);
        $this->assertEquals(1, $tabs['completed']);

        // Check formatOrder mapping for courier_assigned_for_delivery
        $data = $response->json('data');
        $deliveryAssignedOrders = array_filter($data, fn ($o) => $o['status'] === 'DELIVERY_ASSIGNED');
        $this->assertNotEmpty($deliveryAssignedOrders, 'courier_assigned_for_delivery harus terpetakan ke DELIVERY_ASSIGNED');
    }
}
