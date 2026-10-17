<?php

namespace Tests\Feature\Integration;

use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Dispute\DisputeService;
use App\Services\Refund\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\TestFactoryHelper;

/**
 * Tahap 20 — Poin 3: Dispute → Refund requires explicit approval.
 *
 * Verifies that:
 *  - Just opening a dispute does NOT auto-create a refund.
 *  - A refund can only be created with an authorized role (customer
 *    who opened the dispute, store owner/admin, platform admin).
 *  - Auto-refund via dispute resolution requires explicit authorized action.
 */
class DisputeRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }


    public function test_refund_is_not_auto_created_on_dispute_open()
    {
        $customer = TestFactoryHelper::makeUser('customer');
        $owner = TestFactoryHelper::makeUser('store_owner');
        $store = TestFactoryHelper::makeStore($owner)['store'];

        $svc = app(\App\Services\Order\OrderService::class);
        $order = $svc->createOrder([
            'store_id' => $store->id,
            'estimated_weight' => 3,
            'estimated_price' => 30000,
            'items' => [],
        ], $customer);
        $payment = TestFactoryHelper::makePaidPayment($order);

        $disputeSvc = app(DisputeService::class);
        $dispute = $disputeSvc->openDispute(
            $order,
            $customer,
            'lost_item',
            'Some laundry was lost during pickup',
            null
        );

        $this->assertNotNull($dispute->id);
        $this->assertEquals(Dispute::STATUS_COMPLAINT_SUBMITTED, $dispute->status);

        // No refund should have been auto-generated.
        $this->assertEquals(0, Refund::count(),
            'Opening a dispute must not auto-create a refund.');
    }

    public function test_unauthorized_user_cannot_create_refund_for_disputed_order()
    {
        $customer = TestFactoryHelper::makeUser('customer');
        $otherCustomer = TestFactoryHelper::makeUser('customer');
        $owner = TestFactoryHelper::makeUser('store_owner');
        $store = TestFactoryHelper::makeStore($owner)['store'];

        $svc = app(\App\Services\Order\OrderService::class);
        $order = $svc->createOrder([
            'store_id' => $store->id,
            'estimated_weight' => 3,
            'estimated_price' => 30000,
            'items' => [],
        ], $customer);
        $payment = TestFactoryHelper::makePaidPayment($order);

        // otherCustomer (not the order's customer, not store staff) tries to
        // request a refund. RefundPolicy::create must deny.
        config(['xendit.api_key' => 'dummy']);

        $this->actingAs($otherCustomer);
        $r = $this->postJson('/api/refunds', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => 30000,
            'reason' => 'I want money back',
            'method' => 'bank_transfer',
        ]);
        $this->assertEquals(403, $r->status());
        $this->assertEquals(0, Refund::count());
    }

    public function test_store_owner_can_approve_refund_for_own_store_order()
    {
        $customer = TestFactoryHelper::makeUser('customer');
        $owner = TestFactoryHelper::makeUser('store_owner');
        $store = TestFactoryHelper::makeStore($owner)['store'];

        $svc = app(\App\Services\Order\OrderService::class);
        $order = $svc->createOrder([
            'store_id' => $store->id,
            'estimated_weight' => 3,
            'estimated_price' => 30000,
            'items' => [],
        ], $customer);
        $payment = TestFactoryHelper::makePaidPayment($order);

        // Customer requests refund
        $this->actingAs($customer);
        $r = $this->postJson('/api/refunds', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => 30000,
            'reason' => 'Laundry was damaged',
            'method' => 'bank_transfer',
        ]);
        $r->assertStatus(201);
        $refundId = $r->json('id');

        // Refund is REQUESTED (not yet processed)
        $this->assertEquals('requested', Refund::find($refundId)->status);

        // Store owner processes the refund (RefundPolicy allows via userHasStoreAccess).
        // Xendit API call is faked to avoid network. Config xendit.api_key is required.
        config(['xendit.api_key' => 'dummy_test_key']);
        \Illuminate\Support\Facades\Http::fake([
            'api.xendit.co/*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'ref_abc',
                'status' => 'COMPLETED',
            ], 200),
        ]);

        $this->actingAs($owner);
        $r = $this->postJson("/api/refunds/{$refundId}/process");
        $r->assertStatus(200);

        $this->assertEquals('completed', Refund::find($refundId)->fresh()->status);
        $this->assertNotNull(Refund::find($refundId)->completed_at);
    }

    public function test_dispute_resolution_can_trigger_refund_with_authorization()
    {
        $customer = TestFactoryHelper::makeUser('customer');
        $owner = TestFactoryHelper::makeUser('store_owner');
        $admin = TestFactoryHelper::makeUser('platform_admin');
        $store = TestFactoryHelper::makeStore($owner)['store'];

        $svc = app(\App\Services\Order\OrderService::class);
        $order = $svc->createOrder([
            'store_id' => $store->id,
            'estimated_weight' => 3,
            'estimated_price' => 30000,
            'items' => [],
        ], $customer);
        $payment = TestFactoryHelper::makePaidPayment($order);

        // Customer opens dispute
        $disputeSvc = app(DisputeService::class);
        $dispute = $disputeSvc->openDispute(
            $order,
            $customer,
            'damaged_item',
            'My laundry got damaged during washing',
            null
        );

        $this->assertEquals(0, Refund::count(), 'No refund yet — only after resolution');

        // Admin reviews + resolves with outcome "refunded"
        // The DisputeService::resolve() with outcome='refunded' and a
        // refundAmount auto-creates the Refund record (auto-refund flow).
        $this->actingAs($admin);
        $disputeSvc->startReview($dispute, $admin, 'Acknowledged, investigating.');
        $disputeSvc->resolve(
            $dispute,
            $admin,
            'refunded',
            'Refund will be processed within 3 business days.',
            30000 // refundAmount
        );

        // Dispute is now resolved AND a Refund row is auto-created.
        // This is the key integration check: refund does NOT exist before
        // authorized dispute resolution (admin was the actor).
        $this->assertEquals(Dispute::STATUS_RESOLVED, $dispute->fresh()->status);
        $this->assertEquals(1, Refund::count(),
            'Refund must be auto-created ONLY after authorized dispute resolution');

        $autoRefund = Refund::first();
        $this->assertEquals('requested', $autoRefund->status);
        $this->assertEquals(30000.0, (float) $autoRefund->amount);
    }
}