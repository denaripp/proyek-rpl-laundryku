<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\OrderController;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use Tests\TestCase;

class OrderFinancialBreakdownTest extends TestCase
{
    public function test_weighed_order_with_null_commission_rate(): void
    {
        // order 2 kg × Rp7.000 + platform Rp1.000 → service_fee 14000, store_net_income 14000 (rate null)
        $store = new Store(['commission_rate' => null]);
        $order = new Order([
            'status' => 'completed',
            'actual_weight' => 2.0,
            'final_price' => 15000.0,
            'platform_fee' => 1000.0,
            'delivery_fee' => 0.0,
            'pickup_fee' => 0.0,
        ]);
        $order->setRelation('store', $store);
        $order->setRelation('items', collect());

        $breakdown = $order->calculateFinancialBreakdown();

        $this->assertTrue($breakdown['is_weighed']);
        $this->assertEquals(14000.0, $breakdown['service_fee']);
        $this->assertFalse($breakdown['commission_configured']);
        $this->assertEquals(0.0, $breakdown['commission_amount']);
        $this->assertEquals(14000.0, $breakdown['store_net_income']);

        // Test formatOrder includes these fields
        $formatted = app(OrderController::class)->formatOrder($order);
        $this->assertTrue($formatted['is_weighed']);
        $this->assertEquals(14000.0, $formatted['service_fee']);
        $this->assertFalse($formatted['commission_configured']);
        $this->assertEquals(0.0, $formatted['commission_amount']);
        $this->assertEquals(14000.0, $formatted['store_net_income']);
    }

    public function test_unweighed_order_uses_estimated_service_fee(): void
    {
        // Order belum ditimbang → service_fee = estimasi
        $store = new Store(['commission_rate' => null]);
        $order = new Order([
            'status' => 'pending_pickup',
            'actual_weight' => null,
            'estimated_price' => 11500.0,
            'platform_fee' => 1000.0,
            'delivery_fee' => 0.0,
            'pickup_fee' => 0.0,
        ]);
        $item = new OrderItem([
            'price_per_unit' => 7000.0,
            'quantity' => 1.5,
            'total_price' => 10500.0,
        ]);
        $item->line_estimated_amount = 10500.0;
        $order->setRelation('store', $store);
        $order->setRelation('items', collect([$item]));

        $breakdown = $order->calculateFinancialBreakdown();

        $this->assertFalse($breakdown['is_weighed']);
        $this->assertEquals(10500.0, $breakdown['service_fee']);
        $this->assertFalse($breakdown['commission_configured']);
        $this->assertEquals(0.0, $breakdown['commission_amount']);
        $this->assertEquals(10500.0, $breakdown['store_net_income']);

        $formatted = app(OrderController::class)->formatOrder($order);
        $this->assertFalse($formatted['is_weighed']);
        $this->assertEquals(10500.0, $formatted['service_fee']);
    }

    public function test_weighed_order_with_ten_percent_commission_rate(): void
    {
        // Rate 10% → komisi 1400, net 12600
        $store = new Store(['commission_rate' => 10.0]);
        $order = new Order([
            'status' => 'completed',
            'actual_weight' => 2.0,
            'final_price' => 15000.0,
            'platform_fee' => 1000.0,
            'delivery_fee' => 0.0,
            'pickup_fee' => 0.0,
        ]);
        $order->setRelation('store', $store);
        $order->setRelation('items', collect());

        $breakdown = $order->calculateFinancialBreakdown();

        $this->assertTrue($breakdown['is_weighed']);
        $this->assertEquals(14000.0, $breakdown['service_fee']);
        $this->assertTrue($breakdown['commission_configured']);
        $this->assertEquals(10.0, $breakdown['commission_rate']);
        $this->assertEquals(1400.0, $breakdown['commission_amount']);
        $this->assertEquals(12600.0, $breakdown['store_net_income']);

        $formatted = app(OrderController::class)->formatOrder($order);
        $this->assertEquals(1400.0, $formatted['commission_amount']);
        $this->assertEquals(12600.0, $formatted['store_net_income']);
    }
}
