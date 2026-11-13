<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Store;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Services\Order\OrderService;
use App\Services\Payment\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->customerA = User::factory()->create();
        $roleCustomer = \App\Models\Role::where('name', 'customer')->first();
        $this->customerA->roles()->attach($roleCustomer);

        $this->customerB = User::factory()->create();
        $this->customerB->roles()->attach($roleCustomer);

        $this->storeOwner = User::factory()->create();
        $roleStoreOwner = \App\Models\Role::where('name', 'store_owner')->first();
        $this->storeOwner->roles()->attach($roleStoreOwner);

        $this->store = Store::factory()->create([
            'owner_user_id' => $this->storeOwner->id,
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
        $this->orderA = $orderService->createOrder([
            'store_id' => $this->store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [['service_id' => $this->service->id, 'quantity' => 5]],
        ], $this->customerA);

        $this->paymentA = Payment::create([
            'order_id' => $this->orderA->id,
            'payment_method' => 'online',
            'amount' => 50000,
            'status' => 'paid',
            'xendit_invoice_id' => 'inv_123',
            'xendit_payment_id' => 'pay_123',
            'expires_at' => now()->addHour(),
            'paid_at' => now(),
        ]);

        $this->orderB = $orderService->createOrder([
            'store_id' => $this->store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [['service_id' => $this->service->id, 'quantity' => 5]],
        ], $this->customerB);

        $this->paymentB = Payment::create([
            'order_id' => $this->orderB->id,
            'payment_method' => 'online',
            'amount' => 50000,
            'status' => 'pending',
            'xendit_invoice_id' => 'inv_456',
            'expires_at' => now()->addHour(),
        ]);

        config(['xendit.api_key' => 'dummy']);
        config(['xendit.webhook_secret' => 'dummy']);
    }

    public function test_refund_requires_paid_payment()
    {
        $this->actingAs($this->customerB);

        $response = $this->postJson('/api/refunds', [
            'order_id' => $this->orderB->id,
            'payment_id' => $this->paymentB->id,
            'amount' => 50000,
            'reason' => 'Test refund',
            'method' => 'bank_transfer',
        ]);

        $response->assertStatus(500);
        $this->assertStringContainsString('Refund can only be requested for PAID payments', $response->getContent());
    }

    public function test_refund_cannot_exceed_paid_amount()
    {
        $this->actingAs($this->customerA);

        $response = $this->postJson('/api/refunds', [
            'order_id' => $this->orderA->id,
            'payment_id' => $this->paymentA->id,
            'amount' => 60000,
            'reason' => 'Test refund',
            'method' => 'bank_transfer',
        ]);

        $response->assertStatus(500);
        $this->assertStringContainsString('Refund amount cannot exceed paid amount', $response->getContent());
    }

    public function test_refund_request_is_idempotent_duplicate_rejected()
    {
        $this->actingAs($this->customerA);

        $data = [
            'order_id' => $this->orderA->id,
            'payment_id' => $this->paymentA->id,
            'amount' => 50000,
            'reason' => 'Test refund',
            'method' => 'bank_transfer',
        ];

        // First request should succeed
        $response1 = $this->postJson('/api/refunds', $data);
        $response1->assertStatus(201);

        // Second request should fail (duplicate)
        $response2 = $this->postJson('/api/refunds', $data);
        $response2->assertStatus(500);
        $this->assertStringContainsString('A refund is already in progress', $response2->getContent());
    }

    public function test_customer_cannot_request_refund_for_others_order()
    {
        $this->actingAs($this->customerB);

        $response = $this->postJson('/api/refunds', [
            'order_id' => $this->orderA->id,
            'payment_id' => $this->paymentA->id,
            'amount' => 50000,
            'reason' => 'Test refund',
            'method' => 'bank_transfer',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_owner_can_process_refund()
    {
        // First, customer requests refund
        $this->actingAs($this->customerA);
        $response = $this->postJson('/api/refunds', [
            'order_id' => $this->orderA->id,
            'payment_id' => $this->paymentA->id,
            'amount' => 50000,
            'reason' => 'Test refund',
            'method' => 'bank_transfer',
        ]);
        $response->assertStatus(201);
        $refundId = $response->json('id');

        // Fake Xendit refund response
        Http::fake([
            'api.xendit.co/v2/refunds*' => Http::response([
                'id' => 'ref_123',
                'status' => 'COMPLETED',
            ], 200),
        ]);

        // Store owner processes refund
        $this->actingAs($this->storeOwner);
        $processResponse = $this->postJson("/api/refunds/{$refundId}/process");
        $processResponse->assertStatus(200);

        $refund = Refund::find($refundId);
        $this->assertEquals('completed', $refund->status);
        $this->assertNotNull($refund->completed_at);
    }

    public function test_customer_can_view_own_refund()
    {
        // Create refund
        $this->actingAs($this->customerA);
        $response = $this->postJson('/api/refunds', [
            'order_id' => $this->orderA->id,
            'payment_id' => $this->paymentA->id,
            'amount' => 50000,
            'reason' => 'Test refund',
            'method' => 'bank_transfer',
        ]);
        $refundId = $response->json('id');

        // Customer A can view own refund
        $viewResponse = $this->getJson("/api/refunds/{$refundId}");
        $viewResponse->assertStatus(200);
        $this->assertEquals($refundId, $viewResponse->json('id'));
    }

    public function test_customer_cannot_view_others_refund()
    {
        // Create refund for customer A
        $this->actingAs($this->customerA);
        $response = $this->postJson('/api/refunds', [
            'order_id' => $this->orderA->id,
            'payment_id' => $this->paymentA->id,
            'amount' => 50000,
            'reason' => 'Test refund',
            'method' => 'bank_transfer',
        ]);
        $refundId = $response->json('id');

        // Customer B cannot view
        $this->actingAs($this->customerB);
        $viewResponse = $this->getJson("/api/refunds/{$refundId}");
        $viewResponse->assertStatus(403);
    }

    public function test_cannot_request_refund_with_mismatched_payment_id()
    {
        // Customer A owns orderA, but tries to use paymentB (which belongs to orderB)
        $this->actingAs($this->customerA);
        $response = $this->postJson('/api/refunds', [
            'order_id' => $this->orderA->id,
            'payment_id' => $this->paymentB->id,
            'amount' => 50000,
            'reason' => 'Mismatch attack attempt',
            'method' => 'bank_transfer',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Payment does not belong to the specified order', $response->getContent());
    }

    public function test_refund_amount_must_be_strictly_positive()
    {
        $this->actingAs($this->customerA);
        $response = $this->postJson('/api/refunds', [
            'order_id' => $this->orderA->id,
            'payment_id' => $this->paymentA->id,
            'amount' => 0,
            'reason' => 'Zero amount test',
            'method' => 'bank_transfer',
        ]);

        // Form request or service validation rejects non-positive amount
        $this->assertTrue(in_array($response->status(), [422, 500]));
    }
}