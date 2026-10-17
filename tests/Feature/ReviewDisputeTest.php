<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Store;
use App\Models\User;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReviewDisputeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $roleCustomer = Role::where('name', 'customer')->first();
        $this->customerA = User::factory()->create();
        $this->customerA->roles()->attach($roleCustomer);
        $this->customerB = User::factory()->create();
        $this->customerB->roles()->attach($roleCustomer);

        $this->storeOwner = User::factory()->create();
        $this->storeOwner->roles()->attach(Role::where('name', 'store_owner')->first());

        $this->platformAdmin = User::factory()->create();
        $this->platformAdmin->roles()->attach(Role::where('name', 'platform_admin')->first());

        $this->store = Store::factory()->create([
            'owner_user_id' => $this->storeOwner->id,
            'status' => 'approved',
            'is_active' => true,
            'service_radius_km' => 10,
        ]);

        $this->service = Service::create(['name' => 'Laundry', 'is_active' => true]);
        ServicePrice::create([
            'store_id' => $this->store->id,
            'service_id' => $this->service->id,
            'price_per_unit' => 10000,
            'is_active' => true,
        ]);

        $orderService = new OrderService();
        $orderData = [
            'store_id' => $this->store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [['service_id' => $this->service->id, 'quantity' => 5]],
        ];

        $this->orderA = $orderService->createOrder($orderData, $this->customerA);
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

        $this->orderB = $orderService->createOrder($orderData, $this->customerB);

        config(['xendit.api_key' => 'dummy']);
        config(['xendit.webhook_secret' => 'dummy']);
    }

    protected function completeOrder(Order $order): void
    {
        $order->update(['status' => 'completed', 'completed_at' => now()]);
    }

    // ========== REVIEW ==========

    public function test_customer_cannot_review_order_that_is_not_completed()
    {
        // orderA masih status 'created' (belum COMPLETED)
        $this->actingAs($this->customerA);

        $response = $this->postJson("/api/orders/{$this->orderA->id}/reviews", [
            'rating' => 5,
            'comment' => 'Bagus',
        ]);

        $response->assertStatus(500);
        $this->assertStringContainsString('Review can only be created for COMPLETED orders', $response->getContent());
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_customer_can_review_completed_order_and_store_rating_is_recomputed()
    {
        $this->completeOrder($this->orderA);
        $this->actingAs($this->customerA);

        $response = $this->postJson("/api/orders/{$this->orderA->id}/reviews", [
            'rating' => 4,
            'comment' => 'Layanan oke',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reviews', [
            'order_id' => $this->orderA->id,
            'customer_id' => $this->customerA->id,
            'store_id' => $this->store->id,
            'rating' => 4,
        ]);

        // Aggregate store ter-update (precomputed, tanpa query aggregate saat search)
        $this->store->refresh();
        $this->assertEquals(4.00, (float) $this->store->average_rating);
        $this->assertEquals(1, $this->store->review_count);
    }

    public function test_only_one_review_per_order()
    {
        $this->completeOrder($this->orderA);
        $this->actingAs($this->customerA);

        $first = $this->postJson("/api/orders/{$this->orderA->id}/reviews", ['rating' => 5]);
        $first->assertStatus(201);

        $second = $this->postJson("/api/orders/{$this->orderA->id}/reviews", ['rating' => 3]);
        $second->assertStatus(500);
        $this->assertStringContainsString('already been reviewed', $second->getContent());
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_customer_cannot_review_others_order()
    {
        $this->completeOrder($this->orderA);
        $this->actingAs($this->customerB);

        $response = $this->postJson("/api/orders/{$this->orderA->id}/reviews", ['rating' => 5]);
        $response->assertStatus(403);
        $this->assertDatabaseCount('reviews', 0);
    }

    // ========== DISPUTE ==========

    public function test_dispute_flow_records_audit_and_refund_requires_explicit_approval()
    {
        $this->actingAs($this->customerA);

        // 1. Customer buka complaint
        $openResponse = $this->postJson("/api/orders/{$this->orderA->id}/disputes", [
            'reason' => 'damaged_item',
            'description' => 'Baju saya robek setelah dicuci.',
            'evidence' => [['type' => 'image', 'url' => 'https://example.com/foto.jpg']],
        ]);
        $openResponse->assertStatus(201);
        $disputeId = $openResponse->json('id');

        // Audit log tercatat untuk setiap tahap
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->customerA->id,
            'action' => 'dispute_opened',
            'resource_type' => 'disputes',
            'resource_id' => $disputeId,
        ]);

        // 2. Platform Admin mulai review
        $this->actingAs($this->platformAdmin);
        $this->postJson("/api/disputes/{$disputeId}/review", ['note' => 'Cek bukti'])
            ->assertStatus(200);
        $this->assertDatabaseHas('disputes', ['id' => $disputeId, 'status' => 'under_review']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'dispute_review_started', 'resource_id' => $disputeId]);

        // 3. Minta info tambahan
        $this->postJson("/api/disputes/{$disputeId}/info-request", ['question' => 'Mohon foto bagian yang robek'])
            ->assertStatus(200);
        $this->assertDatabaseHas('audit_logs', ['action' => 'dispute_info_requested', 'resource_id' => $disputeId]);

        // 4. Investigation
        $this->postJson("/api/disputes/{$disputeId}/investigate", ['note' => 'Investigasi ke store'])
            ->assertStatus(200);
        $this->assertDatabaseHas('disputes', ['id' => $disputeId, 'status' => 'investigation']);

        // 5. Resolution oleh admin (keputusan manusia) -> outcome refunded
        $resolveResponse = $this->postJson("/api/disputes/{$disputeId}/resolve", [
            'outcome' => 'refunded',
            'resolution_note' => 'Terbukti rusak saat proses pencucian. Full refund disetujui.',
        ]);
        $resolveResponse->assertStatus(200);

        $dispute = Dispute::find($disputeId);
        $this->assertEquals('resolved', $dispute->status);
        $this->assertNotNull($dispute->refund_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'dispute_resolved', 'resource_id' => $disputeId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'dispute_refund_requested', 'resource_id' => $disputeId]);

        // Refund via Refund Service (Tahap 10) dan masih 'requested' —
        // TIDAK otomatis diproses. Harus ada approval eksplisit.
        $refund = Refund::find($dispute->refund_id);
        $this->assertEquals('requested', $refund->status);
        $this->assertEquals('Dispute ' . $dispute->complaint_number, substr($refund->reason, 0, strlen('Dispute ' . $dispute->complaint_number)));

        // 6. Approval + proses eksplisit oleh authorized role baru menuntaskan refund
        Http::fake([
            'api.xendit.co/v2/refunds*' => Http::response(['id' => 'ref_123', 'status' => 'COMPLETED'], 200),
        ]);
        $this->actingAs($this->storeOwner);
        $this->postJson("/api/refunds/{$refund->id}/process")->assertStatus(200);
        $this->assertEquals('completed', $refund->fresh()->status);
    }

    public function test_unresolved_dispute_cannot_trigger_refund_without_explicit_authorized_approval()
    {
        $this->actingAs($this->customerA);
        $openResponse = $this->postJson("/api/orders/{$this->orderA->id}/disputes", [
            'reason' => 'lost_item',
            'description' => 'Satu celana hilang.',
        ]);
        $openResponse->assertStatus(201);
        $disputeId = $openResponse->json('id');

        // Customer (bukan authorized reviewer) tidak bisa resolve -> 403
        $this->postJson("/api/disputes/{$disputeId}/resolve", [
            'outcome' => 'refunded',
            'resolution_note' => 'Refund sendiri',
        ])->assertStatus(403);

        // Store owner juga tidak punya permission resolve_dispute -> 403
        $this->actingAs($this->storeOwner);
        $this->postJson("/api/disputes/{$disputeId}/resolve", [
            'outcome' => 'refunded',
            'resolution_note' => 'Refund dari store',
        ])->assertStatus(403);

        // Dispute masih belum resolved dan BELUM ADA refund yang terbentuk
        $this->assertEquals('complaint_submitted', Dispute::find($disputeId)->status);
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_customer_cannot_review_or_investigate_dispute()
    {
        $this->actingAs($this->customerA);
        $disputeId = $this->postJson("/api/orders/{$this->orderA->id}/disputes", [
            'reason' => 'late_delivery',
            'description' => 'Telat 3 hari.',
        ])->json('id');

        $this->postJson("/api/disputes/{$disputeId}/review")->assertStatus(403);
        $this->postJson("/api/disputes/{$disputeId}/investigate")->assertStatus(403);
        $this->assertEquals('complaint_submitted', Dispute::find($disputeId)->status);
    }

    public function test_dispute_timeline_events_are_recorded()
    {
        $this->actingAs($this->customerA);
        $disputeId = $this->postJson("/api/orders/{$this->orderA->id}/disputes", [
            'reason' => 'poor_quality',
            'description' => 'Hasil cuciannya kotor.',
        ])->json('id');

        $this->actingAs($this->platformAdmin);
        $this->postJson("/api/disputes/{$disputeId}/review")->assertStatus(200);
        $this->postJson("/api/disputes/{$disputeId}/reject", ['note' => 'Tidak terbukti, hasil dicek normal.'])->assertStatus(200);

        $dispute = Dispute::with('events')->find($disputeId);
        $actions = $dispute->events->pluck('action')->toArray();
        $this->assertContains('dispute_opened', $actions);
        $this->assertContains('dispute_review_started', $actions);
        $this->assertContains('dispute_rejected', $actions);
        $this->assertEquals('rejected', $dispute->status);
        $this->assertDatabaseCount('refunds', 0);
    }
}