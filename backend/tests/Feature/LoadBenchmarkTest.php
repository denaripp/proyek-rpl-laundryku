<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoadBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $storeOwner;
    protected Store $store;
    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $customerRole = Role::firstOrCreate(['code' => 'customer'], ['name' => 'Customer']);
        $storeAdminRole = Role::firstOrCreate(['code' => 'store_admin'], ['name' => 'Store Admin']);

        $this->customer = User::factory()->create(['status' => 'active']);
        UserRole::create(['user_id' => $this->customer->id, 'role_id' => $customerRole->id, 'status' => 'active']);

        $this->storeOwner = User::factory()->create(['status' => 'active']);
        UserRole::create(['user_id' => $this->storeOwner->id, 'role_id' => $storeAdminRole->id, 'status' => 'active']);

        // Seed 10 stores
        for ($i = 1; $i <= 10; $i++) {
            Store::create([
                'owner_user_id' => $this->storeOwner->id,
                'name' => "Laundry Cabang {$i}",
                'address_text' => "Jl. Raya No. {$i}, Jakarta",
                'latitude' => -6.200000 + ($i * 0.001),
                'longitude' => 106.816666 + ($i * 0.001),
                'service_radius_km' => 5.0,
                'approval_status' => 'approved',
                'operational_status' => 'active',
            ]);
        }

        $this->store = Store::first();

        // Seed 20 orders
        for ($i = 1; $i <= 20; $i++) {
            Order::create([
                'order_number' => "ORD-BENCH-" . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
                'customer_id' => $this->customer->id,
                'store_id' => $this->store->id,
                'pickup_address_snapshot' => ['address_text' => 'Jl. Test', 'latitude' => -6.2, 'longitude' => 106.8],
                'delivery_address_snapshot' => ['address_text' => 'Jl. Test', 'latitude' => -6.2, 'longitude' => 106.8],
                'status' => 'PENDING_PICKUP',
                'payment_status' => 'UNPAID',
                'estimated_amount' => 25000,
            ]);
        }

        // Seed conversation
        $this->conversation = Conversation::create([
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'status' => 'active',
        ]);
    }

    /**
     * TEST-07 / NFR-TEST-02: Concurrent Load Benchmark on Store Search (50 Virtual Requests).
     */
    public function test_load_benchmark_store_search_50_concurrent_requests(): void
    {
        $startTime = microtime(true);
        $concurrency = 50;
        $successCount = 0;

        for ($i = 0; $i < $concurrency; $i++) {
            $response = $this->getJson('/api/v1/stores?search=Laundry&lat=-6.200&lng=106.816');
            if ($response->status() === 200) {
                $successCount++;
            }
        }

        $totalDurationMs = (microtime(true) - $startTime) * 1000;
        $avgResponseTimeMs = $totalDurationMs / $concurrency;

        $this->assertEquals(50, $successCount);
        $this->assertLessThan(200, $avgResponseTimeMs, "Average response time per search request should be under 200ms (Got {$avgResponseTimeMs}ms)");
    }

    /**
     * TEST-07 / NFR-TEST-02: Concurrent Load Benchmark on Order Listing (50 Virtual Requests).
     */
    public function test_load_benchmark_order_list_50_concurrent_requests(): void
    {
        $startTime = microtime(true);
        $concurrency = 50;
        $successCount = 0;

        for ($i = 0; $i < $concurrency; $i++) {
            $response = $this->actingAs($this->customer, 'sanctum')
                ->getJson('/api/v1/orders');
            if ($response->status() === 200) {
                $successCount++;
            }
        }

        $totalDurationMs = (microtime(true) - $startTime) * 1000;
        $avgResponseTimeMs = $totalDurationMs / $concurrency;

        $this->assertEquals(50, $successCount);
        $this->assertLessThan(200, $avgResponseTimeMs, "Average response time per order list request should be under 200ms (Got {$avgResponseTimeMs}ms)");
    }

    /**
     * TEST-07 / NFR-TEST-02: Concurrent Load Benchmark on Chat Messages (50 Virtual Requests).
     */
    public function test_load_benchmark_chat_messages_50_concurrent_requests(): void
    {
        $startTime = microtime(true);
        $concurrency = 50;
        $successCount = 0;

        for ($i = 0; $i < $concurrency; $i++) {
            // Post message
            $sendRes = $this->actingAs($this->customer, 'sanctum')
                ->postJson("/api/v1/conversations/{$this->conversation->id}/messages", [
                    'body' => "Pesan pengujian beban ke-{$i}",
                ]);

            if ($sendRes->status() === 201) {
                $successCount++;
            }
        }

        $totalDurationMs = (microtime(true) - $startTime) * 1000;
        $avgResponseTimeMs = $totalDurationMs / $concurrency;

        $this->assertEquals(50, $successCount);
        $this->assertLessThan(200, $avgResponseTimeMs, "Average response time per chat message request should be under 200ms (Got {$avgResponseTimeMs}ms)");
    }
}
