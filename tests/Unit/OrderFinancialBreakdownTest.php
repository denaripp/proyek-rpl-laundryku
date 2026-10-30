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

    public function test_items_with_line_final_amount_zero_is_valid(): void
    {
        // Item promo / gratis dengan line_final_amount = 0 adalah valid (bukan dianggap belum diisi)
        $store = new Store(['commission_rate' => 10.0]);
        $order = new Order([
            'status' => 'completed',
            'actual_weight' => 1.0,
            'final_price' => 1000.0,
            'platform_fee' => 1000.0,
            'delivery_fee' => 0.0,
            'pickup_fee' => 0.0,
        ]);
        $item = new OrderItem([
            'price_per_unit' => 0.0,
            'quantity' => 1.0,
            'total_price' => 0.0,
        ]);
        $item->line_final_amount = 0.0;
        $order->setRelation('store', $store);
        $order->setRelation('items', collect([$item]));

        $breakdown = $order->calculateFinancialBreakdown();

        $this->assertEquals(0.0, $breakdown['service_fee']);
        $this->assertEquals(0.0, $breakdown['commission_amount']);
        $this->assertEquals(0.0, $breakdown['store_net_income']);
    }

    public function test_mixed_items_with_zero_and_positive_final_amounts(): void
    {
        // Campuran item: item 1 gratis (0), item 2 berbayar (15.000)
        $store = new Store(['commission_rate' => 10.0]);
        $order = new Order([
            'status' => 'completed',
            'actual_weight' => 2.0,
            'final_price' => 16000.0,
            'platform_fee' => 1000.0,
            'delivery_fee' => 0.0,
            'pickup_fee' => 0.0,
        ]);
        $itemFree = new OrderItem(['price_per_unit' => 0.0, 'quantity' => 1.0, 'total_price' => 0.0]);
        $itemFree->line_final_amount = 0.0;

        $itemPaid = new OrderItem(['price_per_unit' => 15000.0, 'quantity' => 1.0, 'total_price' => 15000.0]);
        $itemPaid->line_final_amount = 15000.0;

        $order->setRelation('store', $store);
        $order->setRelation('items', collect([$itemFree, $itemPaid]));

        $breakdown = $order->calculateFinancialBreakdown();

        $this->assertEquals(15000.0, $breakdown['service_fee']);
        $this->assertEquals(1500.0, $breakdown['commission_amount']);
        $this->assertEquals(13500.0, $breakdown['store_net_income']);
    }

    public function test_item_with_null_line_final_amount_distinguished_from_zero(): void
    {
        // Jika salah satu item masih null, prioritas 1 tidak aktif, fallback ke prioritas 2 (final_price)
        $store = new Store(['commission_rate' => 10.0]);
        $order = new Order([
            'status' => 'completed',
            'actual_weight' => 2.0,
            'final_price' => 18000.0,
            'platform_fee' => 1000.0,
            'delivery_fee' => 0.0,
            'pickup_fee' => 0.0,
        ]);
        $item1 = new OrderItem(['price_per_unit' => 10000.0, 'quantity' => 1.0, 'total_price' => 10000.0]);
        $item1->line_final_amount = null; // belum terisi

        $item2 = new OrderItem(['price_per_unit' => 7000.0, 'quantity' => 1.0, 'total_price' => 7000.0]);
        $item2->line_final_amount = 7000.0;

        $order->setRelation('store', $store);
        $order->setRelation('items', collect([$item1, $item2]));

        $breakdown = $order->calculateFinancialBreakdown();

        // Fallback ke final_price (18000 - 1000 = 17000)
        $this->assertEquals(17000.0, $breakdown['service_fee']);
        $this->assertEquals(1700.0, $breakdown['commission_amount']);
        $this->assertEquals(15300.0, $breakdown['store_net_income']);
    }

    public function test_weighed_order_with_final_price_zero(): void
    {
        // Order dengan harga final 0 (misalnya promo total gratis)
        $store = new Store(['commission_rate' => 10.0]);
        $order = new Order([
            'status' => 'completed',
            'actual_weight' => 1.0,
            'final_price' => 0.0,
            'platform_fee' => 0.0,
            'delivery_fee' => 0.0,
            'pickup_fee' => 0.0,
        ]);
        $order->setRelation('store', $store);
        $order->setRelation('items', collect());

        $breakdown = $order->calculateFinancialBreakdown();

        $this->assertEquals(0.0, $breakdown['service_fee']);
        $this->assertEquals(0.0, $breakdown['commission_amount']);
        $this->assertEquals(0.0, $breakdown['store_net_income']);
    }

    public function test_weighed_order_with_discount_in_pricing_fallback(): void
    {
        // final_price = serviceTotal + fees - discount
        // Contoh: serviceTotal 14000 + platform 1000 - discount 2000 = final_price 13000
        // Maka serviceTotal seharusnya terhitung: 13000 - 1000 + 2000 = 14000
        $store = new Store(['commission_rate' => 10.0]);
        $order = new Order([
            'status' => 'completed',
            'actual_weight' => 2.0,
            'final_price' => 13000.0,
            'platform_fee' => 1000.0,
            'delivery_fee' => 0.0,
            'pickup_fee' => 0.0,
            'discount' => 2000.0,
        ]);
        $order->setRelation('store', $store);
        $order->setRelation('items', collect());

        $breakdown = $order->calculateFinancialBreakdown();

        $this->assertEquals(14000.0, $breakdown['service_fee']);
        $this->assertEquals(1400.0, $breakdown['commission_amount']);
        $this->assertEquals(12600.0, $breakdown['store_net_income']);
    }

    public function test_backward_compatible_response_fields(): void
    {
        $store = new Store(['commission_rate' => 5.0]);
        $order = new Order([
            'status' => 'completed',
            'actual_weight' => 3.0,
            'final_price' => 22000.0,
            'platform_fee' => 1000.0,
            'delivery_fee' => 0.0,
            'pickup_fee' => 0.0,
        ]);
        $order->setRelation('store', $store);
        $order->setRelation('items', collect());

        $formatted = app(OrderController::class)->formatOrder($order);

        $this->assertArrayHasKey('service_fee', $formatted);
        $this->assertArrayHasKey('is_weighed', $formatted);
        $this->assertArrayHasKey('commission_rate', $formatted);
        $this->assertArrayHasKey('commission_amount', $formatted);
        $this->assertArrayHasKey('commission_configured', $formatted);
        $this->assertArrayHasKey('store_net_income', $formatted);

        $this->assertIsFloat($formatted['service_fee']);
        $this->assertIsBool($formatted['is_weighed']);
        $this->assertIsFloat($formatted['commission_rate']);
        $this->assertIsFloat($formatted['commission_amount']);
        $this->assertIsBool($formatted['commission_configured']);
        $this->assertIsFloat($formatted['store_net_income']);
    }
}
