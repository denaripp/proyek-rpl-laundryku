<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\SettlementPeriod;
use App\Models\Store;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Services\Order\OrderService;
use App\Services\Settlement\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // Seed settlement configs (migration may have already inserted them)
        SystemConfiguration::updateOrCreate(['key' => 'SETTLEMENT_PERIOD'], [
            'value' => ['placeholder' => '[SETTLEMENT_PERIOD]', 'interval' => 'weekly'],
            'description' => 'Settlement period',
            'is_editable' => true,
        ]);
        SystemConfiguration::updateOrCreate(['key' => 'MARKETPLACE_COMMISSION'], [
            'value' => ['placeholder' => '[MARKETPLACE_COMMISSION]', 'rate_percent' => 10],
            'description' => 'Commission',
            'is_editable' => true,
        ]);
        SystemConfiguration::updateOrCreate(['key' => 'PLATFORM_FEE'], [
            'value' => ['placeholder' => '[PLATFORM_FEE]', 'fee_type' => 'fixed', 'amount' => 2000],
            'description' => 'Platform fee',
            'is_editable' => true,
        ]);
        SystemConfiguration::updateOrCreate(['key' => 'DISPUTE_SLA'], [
            'value' => ['placeholder' => '[DISPUTE_SLA]', 'resolution_days' => 7, 'first_response_hours' => 24],
            'description' => 'Dispute SLA',
            'is_editable' => true,
        ]);

        $roleCustomer = Role::where('name', 'customer')->first();
        $roleStoreOwner = Role::where('name', 'store_owner')->first();
        $rolePlatformAdmin = Role::where('name', 'platform_admin')->first();

        $this->customer = User::factory()->create();
        $this->customer->roles()->attach($roleCustomer);

        $this->ownerA = User::factory()->create();
        $this->ownerA->roles()->attach($roleStoreOwner);
        $this->ownerB = User::factory()->create();
        $this->ownerB->roles()->attach($roleStoreOwner);

        $this->platformAdmin = User::factory()->create();
        $this->platformAdmin->roles()->attach($rolePlatformAdmin);

        $this->storeA = Store::factory()->create([
            'owner_user_id' => $this->ownerA->id,
            'status' => 'approved',
            'is_active' => true,
            'commission_rate' => null, // use platform default 10%
        ]);
        $this->storeB = Store::factory()->create([
            'owner_user_id' => $this->ownerB->id,
            'status' => 'approved',
            'is_active' => true,
            'commission_rate' => 15, // store-level override
        ]);

        $service = Service::create(['name' => 'Laundry', 'is_active' => true]);
        ServicePrice::create([
            'store_id' => $this->storeA->id,
            'service_id' => $service->id,
            'price_per_unit' => 10000,
            'is_active' => true,
        ]);

        $this->settlementService = new SettlementService();
    }

    protected function makeCompletedOrder(Store $store, User $customer, string $completedAt, float $amount): Order
    {
        $order = Order::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => $amount,
            'total_price' => $amount,
            'completed_at' => $completedAt,
        ]);

        return $order;
    }

    public function test_order_in_open_dispute_is_not_included_in_settlement()
    {
        $windowStart = now()->subDays(20);
        $windowEnd = now()->subDays(8); // both orders outside dispute window (7 days)

        $cleanOrder = $this->makeCompletedOrder($this->storeA, $this->customer, now()->subDays(10), 100000);
        $disputedOrder = $this->makeCompletedOrder($this->storeA, $this->customer, now()->subDays(10), 100000);

        // Open dispute on the second order
        Dispute::create([
            'complaint_number' => 'DSP-TEST-001',
            'order_id' => $disputedOrder->id,
            'opened_by' => $this->customer->id,
            'reason' => 'damaged_item',
            'description' => 'Item was damaged',
            'status' => Dispute::STATUS_UNDER_REVIEW,
        ]);

        $period = $this->settlementService->generateSettlement(
            $this->storeA,
            $this->platformAdmin,
            $windowStart,
            $windowEnd
        );

        // Only the clean order should be settled
        $this->assertEquals(1, $period->order_count);
        $orderIds = $period->items->pluck('order_id')->toArray();
        $this->assertContains($cleanOrder->id, $orderIds);
        $this->assertNotContains($disputedOrder->id, $orderIds);
    }

    public function test_order_within_dispute_window_is_not_settled()
    {
        $windowStart = now()->subDays(5);
        $windowEnd = now();

        // Completed only 2 days ago — still inside 7-day dispute window
        $recentOrder = $this->makeCompletedOrder($this->storeA, $this->customer, now()->subDays(2), 100000);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Settlement window is empty');

        $this->settlementService->generateSettlement(
            $this->storeA,
            $this->platformAdmin,
            $windowStart,
            $windowEnd
        );
    }

    public function test_commission_calculated_correctly_with_platform_default()
    {
        $windowStart = now()->subDays(20);
        $windowEnd = now()->subDays(8);

        $this->makeCompletedOrder($this->storeA, $this->customer, now()->subDays(10), 100000);

        $period = $this->settlementService->generateSettlement(
            $this->storeA,
            $this->platformAdmin,
            $windowStart,
            $windowEnd
        );

        // Default commission 10% of 100000 = 10000
        $this->assertEquals('100000.00', (string) $period->gross_amount);
        $this->assertEquals('10000.00', (string) $period->commission_amount);
        // Platform fee fixed 2000
        $this->assertEquals('2000.00', (string) $period->platform_fee_amount);
        // Net = 100000 - 10000 - 2000 = 88000
        $this->assertEquals('88000.00', (string) $period->net_amount);
        $this->assertEquals(1, $period->order_count);
    }

    public function test_commission_uses_store_level_override()
    {
        $windowStart = now()->subDays(20);
        $windowEnd = now()->subDays(8);

        $this->makeCompletedOrder($this->storeB, $this->customer, now()->subDays(10), 100000);

        $period = $this->settlementService->generateSettlement(
            $this->storeB,
            $this->platformAdmin,
            $windowStart,
            $windowEnd
        );

        // Store B has commission_rate 15% -> 15000
        $this->assertEquals('15000.00', (string) $period->commission_amount);
        $this->assertEquals('83000.00', (string) $period->net_amount); // 100000-15000-2000
    }

    public function test_store_cannot_view_other_stores_settlement()
    {
        $windowStart = now()->subDays(20);
        $windowEnd = now()->subDays(8);

        $this->makeCompletedOrder($this->storeA, $this->customer, now()->subDays(10), 100000);

        $period = $this->settlementService->generateSettlement(
            $this->storeA,
            $this->platformAdmin,
            $windowStart,
            $windowEnd
        );

        // Owner B tries to view store A's settlement via API
        $this->actingAs($this->ownerB);
        $response = $this->getJson("/api/settlements/{$period->id}");
        $response->assertStatus(403);

        // Owner A can view their own
        $this->actingAs($this->ownerA);
        $response = $this->getJson("/api/settlements/{$period->id}");
        $response->assertStatus(200);
        $this->assertEquals($period->id, $response->json('id'));
    }

    public function test_settlement_list_is_tenant_isolated()
    {
        $windowStart = now()->subDays(20);
        $windowEnd = now()->subDays(8);

        $this->makeCompletedOrder($this->storeA, $this->customer, now()->subDays(10), 100000);
        $this->makeCompletedOrder($this->storeB, $this->customer, now()->subDays(10), 100000);

        $this->settlementService->generateSettlement($this->storeA, $this->platformAdmin, $windowStart, $windowEnd);
        $this->settlementService->generateSettlement($this->storeB, $this->platformAdmin, $windowStart, $windowEnd);

        // Owner A sees only store A settlements
        $this->actingAs($this->ownerA);
        $response = $this->getJson('/api/settlements');
        $response->assertStatus(200);
        $storeIds = collect($response->json('data'))->pluck('store_id')->unique()->toArray();
        $this->assertCount(1, $storeIds);
        $this->assertEquals($this->storeA->id, $storeIds[0]);

        // Platform admin sees all
        $this->actingAs($this->platformAdmin);
        $response = $this->getJson('/api/settlements');
        $response->assertStatus(200);
        $storeIds = collect($response->json('data'))->pluck('store_id')->unique()->toArray();
        $this->assertCount(2, $storeIds);
    }

    public function test_settlement_generation_is_audited()
    {
        $windowStart = now()->subDays(20);
        $windowEnd = now()->subDays(8);

        $this->makeCompletedOrder($this->storeA, $this->customer, now()->subDays(10), 100000);

        $this->settlementService->generateSettlement(
            $this->storeA,
            $this->platformAdmin,
            $windowStart,
            $windowEnd
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'settlement_generated',
            'resource_type' => 'settlement_periods',
        ]);
    }

    public function test_order_cannot_be_settled_twice()
    {
        $windowStart = now()->subDays(20);
        $windowEnd = now()->subDays(8);

        $this->makeCompletedOrder($this->storeA, $this->customer, now()->subDays(10), 100000);

        $this->settlementService->generateSettlement(
            $this->storeA,
            $this->platformAdmin,
            $windowStart,
            $windowEnd
        );

        // Second attempt on overlapping window should fail
        $this->expectException(\Exception::class);

        $this->settlementService->generateSettlement(
            $this->storeA,
            $this->platformAdmin,
            $windowStart,
            $windowEnd
        );
    }

    public function test_customer_cannot_generate_settlement()
    {
        $this->actingAs($this->customer);

        $response = $this->postJson("/api/stores/{$this->storeA->id}/settlements", []);
        $response->assertStatus(403);
    }

    public function test_store_owner_can_generate_own_settlement()
    {
        $windowStart = now()->subDays(20);
        $windowEnd = now()->subDays(8);

        $this->makeCompletedOrder($this->storeA, $this->customer, now()->subDays(10), 100000);

        $this->actingAs($this->ownerA);

        $response = $this->postJson("/api/stores/{$this->storeA->id}/settlements", [
            'from' => $windowStart->toDateString(),
            'to' => $windowEnd->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertEquals(1, $response->json('order_count'));
    }
}