<?php

namespace Tests\Feature\Integration;

use App\Enums\OrderStatus;
use App\Models\Dispute;
use App\Services\Dispute\DisputeService;
use App\Services\Order\OrderService;
use App\Services\Settlement\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\TestFactoryHelper;

/**
 * Tahap 20 — Poin 5: Settlement exclusion for disputed orders.
 *
 * Verifies:
 *  - Orders with an OPEN dispute are NOT included in the next settlement
 *    period (held until dispute is closed).
 *  - After the dispute is closed (resolved/rejected), the order becomes
 *    eligible in the NEXT period (NOT auto-added to existing).
 *  - Orders within the dispute window (regardless of dispute) are also
 *    excluded (already covered by SettlementService::eligibleOrders, but
 *    tested explicitly here as integration).
 */
class SettlementExclusionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }


    public function test_order_with_open_dispute_is_excluded_from_settlement()
    {
        $customer = TestFactoryHelper::makeUser('customer');
        $owner = TestFactoryHelper::makeUser('store_owner');
        $store = TestFactoryHelper::makeStore($owner)['store'];

        $orderSvc = app(OrderService::class);

        // Create 2 completed orders. Both paid.
        $orderClean = $this->createCompletedOrder($orderSvc, $customer, $store);
        $orderDisputed = $this->createCompletedOrder($orderSvc, $customer, $store);

        // Open a dispute on the second order.
        $disputeSvc = app(DisputeService::class);
        $disputeSvc->openDispute(
            $orderDisputed,
            $customer,
            'damaged_item',
            'My laundry was damaged',
            null
        );

        // Force both orders to be OUTSIDE the dispute window (completed 30 days ago)
        DB::table('orders')->whereIn('id', [$orderClean->id, $orderDisputed->id])
            ->update(['completed_at' => now()->subDays(30)]);

        // Generate settlement for last 60 days ending now
        $settlementSvc = app(SettlementService::class);
        $period = $settlementSvc->generateSettlement(
            $store,
            $owner,
            now()->subDays(60),
            now()
        );

        // Settlement items
        $orderIds = DB::table('settlement_items')
            ->where('settlement_period_id', $period->id)
            ->pluck('order_id')
            ->toArray();

        $this->assertContains($orderClean->id, $orderIds,
            'Clean order must be in settlement items');
        $this->assertNotContains($orderDisputed->id, $orderIds,
            'Disputed order must NOT be in settlement items');

        $this->assertCount(1, $orderIds);
        $this->assertEquals(1, $period->order_count);
    }

    public function test_order_within_dispute_window_excluded_regardless_of_dispute()
    {
        $customer = TestFactoryHelper::makeUser('customer');
        $owner = TestFactoryHelper::makeUser('store_owner');
        $store = TestFactoryHelper::makeStore($owner)['store'];

        $orderSvc = app(OrderService::class);

        // Completed but completed_at is RECENT (within dispute window)
        $order = $this->createCompletedOrder($orderSvc, $customer, $store);
        // completed_at = now (within default 7-day window)
        $order->completed_at = now()->subDays(2);
        $order->save();

        $settlementSvc = app(SettlementService::class);
        $period = $settlementSvc->generateSettlement(
            $store,
            $owner,
            now()->subDays(60),
            now()
        );

        $orderIds = DB::table('settlement_items')
            ->where('settlement_period_id', $period->id)
            ->pluck('order_id')
            ->toArray();

        $this->assertNotContains($order->id, $orderIds,
            'Order within dispute window must not be settled yet');
        $this->assertEquals(0, $period->order_count);
    }

    public function test_resolved_dispute_releases_order_for_next_settlement()
    {
        $customer = TestFactoryHelper::makeUser('customer');
        $owner = TestFactoryHelper::makeUser('store_owner');
        $admin = TestFactoryHelper::makeUser('platform_admin');
        $store = TestFactoryHelper::makeStore($owner)['store'];

        $orderSvc = app(OrderService::class);
        $disputeSvc = app(DisputeService::class);
        $settlementSvc = app(SettlementService::class);

        $order = $this->createCompletedOrder($orderSvc, $customer, $store);
        $order->completed_at = now()->subDays(30);
        $order->save();

        $dispute = $disputeSvc->openDispute($order, $customer, 'lost_item', 'Lost during pickup');

        // First settlement: order excluded due to OPEN dispute.
        $period1 = $settlementSvc->generateSettlement(
            $store, $owner, now()->subDays(60), now()
        );
        $this->assertEquals(0, $period1->order_count,
            'Order with open dispute must be excluded from settlement');

        // Admin reviews and resolves the dispute (REJECTED — no refund).
        $this->actingAs($admin);
        $disputeSvc->startReview($dispute, $admin, 'Investigating.');
        $disputeSvc->reject($dispute, $admin, 'No evidence of loss, claim rejected.');

        // Now order is eligible. Verify by calling eligibleOrders() directly
        // (bypasses generateSettlement overlap check, which is unrelated
        // to the dispute exclusion behavior we want to verify).
        $orderIds = collect($settlementSvc->eligibleOrders(
            $store, now()->subDays(60), now()
        ))->pluck('id')->toArray();

        $this->assertContains($order->id, $orderIds,
            'After dispute resolution, order becomes eligible for settlement');
        $this->assertCount(1, $orderIds);
    }

    /**
     * Helper: create a completed, paid order with completed_at set.
     */
    private function createCompletedOrder(OrderService $svc, $customer, $store)
    {
        $order = $svc->createOrder([
            'store_id' => $store->id,
            'estimated_weight' => 3,
            'estimated_price' => 30000,
            'items' => [],
        ], $customer);

        TestFactoryHelper::makePaidPayment($order);
        $order->payment_status = 'paid';
        $order->completed_at = now();
        $order->status = OrderStatus::COMPLETED->value;
        $order->save();

        return $order;
    }
}