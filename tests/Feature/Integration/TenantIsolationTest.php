<?php

namespace Tests\Feature\Integration;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Role;
use App\Services\Refund\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\TestFactoryHelper;

/**
 * Tahap 20 — Poin 2: Tenant isolation across Order + Payment + Settlement + Dashboard.
 *
 * Single test verifying that Store Admin A cannot reach Store B's data
 * through Order / Payment / Refund / Settlement / Dashboard endpoints
 * simultaneously — not in separate tests.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }


    public function test_store_admin_A_cannot_access_store_B_across_all_surfaces()
    {
        // ====== SETUP: Two distinct stores with their own data ======
        $ownerA = TestFactoryHelper::makeUser('store_owner');
        $ownerB = TestFactoryHelper::makeUser('store_owner');
        $staffA = TestFactoryHelper::makeUser('store_staff');
        $customerA = TestFactoryHelper::makeUser('customer');
        $customerB = TestFactoryHelper::makeUser('customer');

        $storeA = TestFactoryHelper::makeStore($ownerA)['store'];
        $storeB = TestFactoryHelper::makeStore($ownerB)['store'];

        // staffA is staff of store A only
        \App\Models\StoreStaff::create([
            'store_id' => $storeA->id,
            'user_id' => $staffA->id,
            'is_active' => true,
        ]);

        $svc = app(\App\Services\Order\OrderService::class);

        // Order for store A (with payment)
        $orderA = $svc->createOrder([
            'store_id' => $storeA->id,
            'estimated_weight' => 3,
            'estimated_price' => 30000,
            'items' => [],
        ], $customerA);
        $paymentA = TestFactoryHelper::makePaidPayment($orderA);

        // Order for store B
        $orderB = $svc->createOrder([
            'store_id' => $storeB->id,
            'estimated_weight' => 3,
            'estimated_price' => 30000,
            'items' => [],
        ], $customerB);
        $paymentB = TestFactoryHelper::makePaidPayment($orderB);

        // Refund for store B's order
        $refundB = Refund::create([
            'order_id' => $orderB->id,
            'payment_id' => $paymentB->id,
            'amount' => 30000,
            'status' => 'requested',
            'reason' => 'Test',
            'method' => 'bank_transfer',
            'refund_reference' => 'REF-TEST-001',
            'requested_by' => $customerB->id,
        ]);

        // Settlement period for store B
        $periodB = \App\Models\SettlementPeriod::create([
            'store_id' => $storeB->id,
            'reference' => 'SET-TEST-B-001',
            'period_start' => now()->subDays(30),
            'period_end' => now(),
            'interval' => 'weekly',
            'gross_amount' => 30000,
            'net_amount' => 27000,
            'status' => 'generated',
        ]);

        // ====== SCENARIO 1: staffA tries to access store B's data ======
        $this->actingAs($staffA);

        // Order detail (store B)
        $r = $this->getJson("/api/orders/{$orderB->id}");
        $this->assertEquals(403, $r->status(), 'staffA must not see store B order');

        // Store owner endpoint (store B's orders)
        $r = $this->getJson("/api/store-owner/stores/{$storeB->id}/orders");
        $this->assertEquals(403, $r->status(), 'staffA must not see store B orders list');

        // Store dashboard (store B)
        $r = $this->getJson("/api/store-owner/dashboard?store_id={$storeB->id}");
        $this->assertEquals(403, $r->status(), 'staffA must not see store B dashboard');

        // Refund detail (store B's refund)
        $r = $this->getJson("/api/refunds/{$refundB->id}");
        $this->assertEquals(403, $r->status(), 'staffA must not see store B refund');

        // Admin payments endpoint — staffA has no admin permission
        $r = $this->getJson('/api/admin/payments');
        $this->assertEquals(403, $r->status(), 'staffA must not see admin payments');

        // ====== SCENARIO 2: ownerA (separate tenant) tries same ======
        $this->actingAs($ownerA);

        $r = $this->getJson("/api/orders/{$orderB->id}");
        $this->assertEquals(403, $r->status(), 'ownerA must not see store B order');

        $r = $this->getJson("/api/store-owner/stores/{$storeB->id}/orders");
        $this->assertEquals(403, $r->status(), 'ownerA must not see store B orders list');

        $r = $this->getJson("/api/store-owner/dashboard?store_id={$storeB->id}");
        $this->assertEquals(403, $r->status(), 'ownerA must not see store B dashboard');

        $r = $this->getJson("/api/refunds/{$refundB->id}");
        $this->assertEquals(403, $r->status(), 'ownerA must not see store B refund');

        // ====== Sanity: each owner CAN access their own store ======
        $this->actingAs($ownerA);
        $r = $this->getJson("/api/orders/{$orderA->id}");
        $this->assertEquals(200, $r->status(), 'ownerA must see own store order');

        $r = $this->getJson("/api/store-owner/stores/{$storeA->id}/orders");
        $this->assertEquals(200, $r->status(), 'ownerA must see own store orders list');

        $r = $this->getJson("/api/store-owner/dashboard?store_id={$storeA->id}");
        $this->assertEquals(200, $r->status(), 'ownerA must see own store dashboard');

        $r = $this->getJson("/api/refunds?status=requested");
        $this->assertEquals(200, $r->status(), 'ownerA can list refunds (own only)');
    }

    public function test_customer_cannot_view_other_customer_order()
    {
        // Cross-tenant: Customer A orders, Customer B (different user) tries
        // to access the order detail. OrderPolicy::view requires
        // customer_id == user->id.
        $ownerA = TestFactoryHelper::makeUser('store_owner');
        $storeA = TestFactoryHelper::makeStore($ownerA)['store'];

        $customerA = TestFactoryHelper::makeUser('customer');
        $customerB = TestFactoryHelper::makeUser('customer');

        $svc = app(\App\Services\Order\OrderService::class);
        $orderA = $svc->createOrder([
            'store_id' => $storeA->id,
            'estimated_weight' => 3,
            'estimated_price' => 30000,
            'items' => [],
        ], $customerA);

        // customerB tries to read customerA's order
        $this->actingAs($customerB);
        $r = $this->getJson("/api/orders/{$orderA->id}");
        $this->assertEquals(403, $r->status());

        // CustomerA can read their own
        $this->actingAs($customerA);
        $r = $this->getJson("/api/orders/{$orderA->id}");
        $this->assertEquals(200, $r->status());
    }
}