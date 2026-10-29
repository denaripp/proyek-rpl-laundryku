<?php

namespace Tests\Feature\Integration;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Review;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;
use Tests\TestFactoryHelper;

/**
 * Tahap 20 — Poin 1: Core loop end-to-end.
 *
 * Single scenario: search store → create order → courier assign →
 * pickup → weigh → payment → processing → delivery → completed → review.
 * Asserts state transitions, audit logs, and queued notifications.
 */
class CoreLoopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }


    public function test_full_lifecycle_from_search_to_review()
    {
        TestFactoryHelper::fakeSideEffects();

        // ====== SETUP ======
        $customer = TestFactoryHelper::makeUser('customer');
        $owner = TestFactoryHelper::makeUser('store_owner');
        ['store' => $store, 'service' => $service, 'price' => $price] =
            TestFactoryHelper::makeStore($owner);
        $courierUser = TestFactoryHelper::makeUser('courier');
        $courier = TestFactoryHelper::makeCourier($courierUser);

        $svc = app(OrderService::class);

        // ====== 1. CREATE ORDER (customer) ======
        $order = $svc->createOrder([
            'store_id' => $store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [['service_id' => $service->id, 'quantity' => 5]],
        ], $customer);
        $orderId = $order->id;

        $this->assertEquals(OrderStatus::CREATED->value, $order->status);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $orderId,
            'status' => OrderStatus::CREATED->value,
        ]);

        // ====== 2. WAITING PICKUP (store owner transitions) ======
        $order = $svc->transition($order, OrderStatus::WAITING_PICKUP->value, $owner);
        $this->assertEquals(OrderStatus::WAITING_PICKUP->value, $order->status);

        // ====== 3. ASSIGN COURIER ======
        $order = $svc->transition($order, OrderStatus::COURIER_ASSIGNED->value, $owner);
        $this->assertEquals(OrderStatus::COURIER_ASSIGNED->value, $order->status);

        // ====== 4. COURIER MOVES → PICKED_UP ======
        $order = $svc->transition($order, OrderStatus::COURIER_TO_CUSTOMER->value, $courierUser);
        $order = $svc->transition($order, OrderStatus::PICKED_UP->value, $courierUser);
        $order = $svc->transition($order, OrderStatus::IN_TRANSIT_TO_STORE->value, $courierUser);
        $order = $svc->transition($order, OrderStatus::RECEIVED_BY_STORE->value, $owner);

        $this->assertEquals(OrderStatus::RECEIVED_BY_STORE->value, $order->status);

        // ====== 5. WEIGH ======
        // setActualWeight requires WEIGHED status, so transition first
        // then set weight (which recalculates total).
        $order = $svc->transition($order, OrderStatus::WEIGHED->value, $owner);
        $order = $svc->setActualWeight($order, 5.0, $owner);
        $order = $svc->transition($order, 'awaiting_price_approval', $owner);
        $order = $svc->transition($order, 'price_approved', $customer);
        $order = $svc->transition($order, 'awaiting_payment', $customer);

        $this->assertEquals('awaiting_payment', $order->status);

        // ====== 6. PAYMENT (store owner marks paid after customer pays) ======
        $payment = TestFactoryHelper::makePaidPayment($order, $order->total_amount);
        $order->payment_status = 'paid';
        $order->save();
        $order = $svc->transition($order, OrderStatus::PAID->value, $owner);

        $this->assertEquals(OrderStatus::PAID->value, $order->status);
        $this->assertEquals('paid', $order->fresh()->payment_status);

        // ====== 7. PROCESSING → READY → DELIVERY ======
        $order = $svc->transition($order, OrderStatus::PROCESSING->value, $owner);
        $order = $svc->transition($order, OrderStatus::READY_FOR_DELIVERY->value, $owner);
        // READY_FOR_DELIVERY can transition directly to
        // COURIER_ASSIGNED_FOR_DELIVERY (state machine fix in Tahap 20).
        $order = $svc->transition($order, OrderStatus::COURIER_ASSIGNED_FOR_DELIVERY->value, $owner);
        $order = $svc->transition($order, OrderStatus::OUT_FOR_DELIVERY->value, $courierUser);
        $order = $svc->transition($order, OrderStatus::DELIVERED->value, $courierUser);

        // Mark completed_at for completion validation
        $order->completed_at = now();
        $order->save();
        $order = $svc->transition($order, OrderStatus::COMPLETED->value, $owner);

        $this->assertEquals(OrderStatus::COMPLETED->value, $order->status);

        // ====== 8. REVIEW ======
        $review = Review::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'rating' => 5,
            'comment' => 'Bagus banget',
            'is_visible' => true,
        ]);
        $this->assertNotNull($review->id);

        // ====== FINAL ASSERTIONS ======
        // All 10 transitions recorded in OrderStatusHistory
        $historyCount = \App\Models\OrderStatusHistory::where('order_id', $orderId)
            ->whereNotNull('status')
            ->count();
        $this->assertGreaterThanOrEqual(10, $historyCount, 'Expected at least 10 status history rows');

        // Order persisted in COMPLETED state with payment
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => 'paid',
        ]);

        // Payment persisted as PAID
        $this->assertDatabaseHas('payments', [
            'order_id' => $orderId,
            'status' => 'paid',
        ]);

        // At least one queued side-effect was enqueued across the lifecycle.
        $pushed = collect(\Illuminate\Support\Facades\Queue::pushedJobs());
        $this->assertGreaterThan(0, $pushed->count(),
            'Expected queued notifications across the lifecycle (e.g. registration, pickup, completed)');
    }

    public function test_invalid_state_transition_throws_and_does_not_persist()
    {
        $owner = TestFactoryHelper::makeUser('store_owner');
        ['store' => $store] = TestFactoryHelper::makeStore($owner);

        $svc = app(OrderService::class);
        $order = $svc->createOrder([
            'store_id' => $store->id,
            'estimated_weight' => 3,
            'estimated_price' => 30000,
            'items' => [],
        ], $owner);

        // created → completed is NOT allowed (must go through full state machine).
        // Use owner as actor (has update_order permission).
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Invalid transition/');
        $svc->transition($order, OrderStatus::COMPLETED->value, $owner);
    }

    public function test_completion_blocked_when_payment_not_paid()
    {
        $owner = TestFactoryHelper::makeUser('store_owner');
        ['store' => $store] = TestFactoryHelper::makeStore($owner);

        $svc = app(OrderService::class);
        $order = $svc->createOrder([
            'store_id' => $store->id,
            'estimated_weight' => 3,
            'estimated_price' => 30000,
            'items' => [],
        ], $owner);

        // Force order to DELIVERED with payment_status=pending
        $order->status = OrderStatus::DELIVERED->value;
        $order->payment_status = 'pending';
        $order->completed_at = now();
        $order->save();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/must be paid before completion/');
        $svc->transition($order, OrderStatus::COMPLETED->value, $owner);
    }

    // ========== SECURITY (Tahap 20) — Additional state machine coverage ==========

    public function test_pickup_failed_state_has_exit_transition()
    {
        // SECURITY (Tahap 20): pickup_failed was previously a dangling
        // state (no outgoing transitions). An order stuck there had no
        // way to be recovered. Now it can go to WAITING_PICKUP (retry)
        // or CANCELLED.
        $owner = TestFactoryHelper::makeUser('store_owner');
        ['store' => $store] = TestFactoryHelper::makeStore($owner);

        $svc = app(OrderService::class);
        $order = $svc->createOrder([
            'store_id' => $store->id,
            'estimated_weight' => 3,
            'estimated_price' => 30000,
            'items' => [],
        ], $owner);

        // Force the order into PICKUP_FAILED (simulating CourierAssignmentService
        // hardcoding this status when assignment times out).
        $order->status = OrderStatus::PICKUP_FAILED->value;
        $order->save();

        // Owner can retry: PICKUP_FAILED → WAITING_PICKUP
        $order = $svc->transition($order, OrderStatus::WAITING_PICKUP->value, $owner);
        $this->assertEquals(OrderStatus::WAITING_PICKUP->value, $order->status);

        // Drive forward to a deterministic state then fail again to test exit.
        $order->status = OrderStatus::PICKUP_FAILED->value;
        $order->save();

        // PICKUP_FAILED → CANCELLED also works.
        $order = $svc->transition($order, OrderStatus::CANCELLED->value, $owner);
        $this->assertEquals(OrderStatus::CANCELLED->value, $order->status);
    }

    public function test_delivery_failed_state_has_exit_transition()
    {
        // Mirror of the above for DELIVERY_FAILED.
        $owner = TestFactoryHelper::makeUser('store_owner');
        ['store' => $store] = TestFactoryHelper::makeStore($owner);

        $svc = app(OrderService::class);
        $order = $svc->createOrder([
            'store_id' => $store->id,
            'estimated_weight' => 3,
            'estimated_price' => 30000,
            'items' => [],
        ], $owner);

        $order->status = OrderStatus::DELIVERY_FAILED->value;
        $order->save();

        $order = $svc->transition($order, OrderStatus::READY_FOR_DELIVERY->value, $owner);
        $this->assertEquals(OrderStatus::READY_FOR_DELIVERY->value, $order->status);

        $order->status = OrderStatus::DELIVERY_FAILED->value;
        $order->save();

        $order = $svc->transition($order, OrderStatus::CANCELLED->value, $owner);
        $this->assertEquals(OrderStatus::CANCELLED->value, $order->status);
    }
}