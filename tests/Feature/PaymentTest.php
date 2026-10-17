<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Models\Store;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Enums\OrderStatus;
use App\Services\Order\OrderService;
use App\Services\Payment\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->customer = User::factory()->create();
        $roleCustomer = \App\Models\Role::where('name', 'customer')->first();
        $this->customer->roles()->attach($roleCustomer);

        $this->store = Store::factory()->create([
            'status' => 'approved',
            'is_active' => true,
            'service_radius_km' => 10,
        ]);

        $this->service = Service::create(['name' => 'Laundry', 'is_active' => true]);
        $this->servicePrice = ServicePrice::create([
            'store_id' => $this->store->id,
            'service_id' => $this->service->id,
            'price_per_unit' => 10000,
            'is_active' => true,
        ]);

        $orderService = new OrderService();
        $this->order = $orderService->createOrder([
            'store_id' => $this->store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [
                ['service_id' => $this->service->id, 'quantity' => 5],
            ],
        ], $this->customer);

        // Set Xendit API key for tests
        config(['xendit.api_key' => 'test_key']);
        config(['xendit.webhook_secret' => 'test_secret']);
        config(['xendit.callback_url' => 'http://localhost/callback']);
    }

    public function test_xendit_create_invoice_success()
    {
        Http::fake([
            'api.xendit.co/v2/invoices*' => Http::response([
                'id' => 'inv_123',
                'invoice_url' => 'https://xendit.co/inv/123',
                'status' => 'PENDING',
            ], 200),
        ]);

        $service = new XenditService();
        $result = $service->createInvoice($this->order);

        $this->assertArrayHasKey('payment_id', $result);
        $this->assertEquals('pending', $result['status']);
        $this->assertArrayHasKey('invoice_url', $result);

        $payment = Payment::where('order_id', $this->order->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('inv_123', $payment->xendit_invoice_id);
        $this->assertNotNull($payment->idempotency_key);
    }

    public function test_duplicate_webhook_is_idempotent()
    {
        // Create payment
        $payment = Payment::create([
            'order_id' => $this->order->id,
            'payment_method' => 'online',
            'amount' => 50000,
            'status' => 'pending',
            'xendit_invoice_id' => 'inv_123',
            'expires_at' => now()->addHour(),
        ]);

        $service = new XenditService();

        $webhookData = [
            'id' => 'inv_123',
            'event_id' => 'evt_456',
            'status' => 'PAID',
            'amount' => 50000,
            'event' => 'invoice.paid',
        ];

        // First call
        $service->processWebhook($webhookData, 'invoice.paid');

        $payment->refresh();
        $this->assertEquals('paid', $payment->status);

        // Second call (duplicate)
        $service->processWebhook($webhookData, 'invoice.paid');

        $payment->refresh();
        $this->assertEquals('paid', $payment->status);

        // Ensure only one PaymentEvent created
        $events = PaymentEvent::where('payment_id', $payment->id)->where('event_type', 'invoice.paid')->get();
        $this->assertCount(1, $events);
    }

    public function test_webhook_amount_mismatch_rejected()
    {
        $payment = Payment::create([
            'order_id' => $this->order->id,
            'payment_method' => 'online',
            'amount' => 50000,
            'status' => 'pending',
            'xendit_invoice_id' => 'inv_123',
            'expires_at' => now()->addHour(),
        ]);

        $service = new XenditService();

        $webhookData = [
            'id' => 'inv_123',
            'event_id' => 'evt_456',
            'status' => 'PAID',
            'amount' => 60000, // mismatch
            'event' => 'invoice.paid',
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Amount mismatch');

        $service->processWebhook($webhookData, 'invoice.paid');
    }

    public function test_expired_payment_cannot_become_paid_via_webhook()
    {
        $payment = Payment::create([
            'order_id' => $this->order->id,
            'payment_method' => 'online',
            'amount' => 50000,
            'status' => 'pending',
            'xendit_invoice_id' => 'inv_123',
            'expires_at' => now()->subHour(),
        ]);

        $service = new XenditService();

        $webhookData = [
            'id' => 'inv_123',
            'event_id' => 'evt_456',
            'status' => 'PAID',
            'amount' => 50000,
            'event' => 'invoice.paid',
        ];

        $service->processWebhook($webhookData, 'invoice.paid');

        $payment->refresh();
        $this->assertNotEquals('paid', $payment->status);
        $this->assertTrue($payment->metadata['reconciliation_needed'] ?? false);
    }

    public function test_cod_verification_placeholder()
    {
        $orderService = new OrderService();
        $order = $orderService->createOrder([
            'store_id' => $this->store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [
                ['service_id' => $this->service->id, 'quantity' => 5],
            ],
        ], $this->customer);

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50000,
            'status' => 'pending',
            'payment_method' => 'COD',
            'expires_at' => now()->addHour(),
        ]);

        $service = new XenditService();
        $result = $service->verifyCodPayment($order, $payment, 'courier_confirmation', [
            'courier_id' => 1,
            'order_id' => $order->id,
            'amount' => 50000,
            'timestamp' => now()->toISOString(),
        ]);

        $this->assertTrue($result);
        $payment->refresh();
        $this->assertEquals('paid', $payment->status);
        $this->assertEquals('paid', $order->payment_status);

        $event = PaymentEvent::where('payment_id', $payment->id)->where('event_type', 'cod_verified')->first();
        $this->assertNotNull($event);
    }
}