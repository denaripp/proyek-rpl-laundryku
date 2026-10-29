<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreSidebarCountsSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_counts_respects_store_id_for_multi_store_owner()
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_STORE_ADMIN,
        ]);

        $store1 = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
        ]);

        $store2 = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
        ]);

        // 3 new orders in store 1
        Order::factory()->count(3)->create([
            'store_id' => $store1->id,
            'status' => 'pending_pickup',
        ]);

        // 1 new order in store 2
        Order::factory()->count(1)->create([
            'store_id' => $store2->id,
            'status' => 'created',
        ]);

        Sanctum::actingAs($owner, ['role:store_admin']);

        // Request for store 1
        $res1 = $this->getJson("/api/v1/store/sidebar-counts?store_id={$store1->id}");
        $res1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pesanan_baru' => 3,
                    'store_id' => (string) $store1->id,
                ],
            ]);

        // Request for store 2
        $res2 = $this->getJson("/api/v1/store/sidebar-counts?store_id={$store2->id}");
        $res2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pesanan_baru' => 1,
                    'store_id' => (string) $store2->id,
                ],
            ]);
    }

    public function test_sidebar_counts_matches_candidate_courier_count()
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_STORE_ADMIN,
        ]);

        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
        ]);

        // Candidate 1: unverified courier applied to store
        Courier::factory()->create([
            'store_id' => $store->id,
            'is_verified' => false,
        ]);

        // Candidate 2: unassigned verified courier available for recruitment
        $c2 = Courier::factory()->create([
            'store_id' => null,
            'is_verified' => true,
        ]);

        // Candidate 3: rejected courier (should not be counted)
        $c3 = Courier::factory()->create([
            'store_id' => null,
            'is_verified' => true,
        ]);

        AuditLog::create([
            'actor_id' => $owner->id,
            'action' => 'STORE_COURIER_REJECT',
            'resource_type' => 'courier',
            'resource_id' => (string) $c3->id,
            'after_value' => json_encode(['store_id' => $store->id]),
        ]);

        Sanctum::actingAs($owner, ['role:store_admin']);

        $res = $this->getJson("/api/v1/store/sidebar-counts?store_id={$store->id}");
        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pelamar_kurir_pending' => 2,
                ],
            ]);
    }
}
