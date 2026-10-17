<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Rating;
use App\Models\Role;
use App\Models\Store;
use App\Models\StoreService;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase11Test extends TestCase
{
    use RefreshDatabase;

    protected User $customerA;
    protected User $customerB;
    protected User $storeOwner;
    protected Store $store;
    protected Order $orderCompleted;
    protected Order $orderProcessing;
    protected Order $orderCustomerB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'level' => 100]);
        Role::firstOrCreate(['code' => 'courier'], ['name' => 'Kurir', 'level' => 30]);
        Role::firstOrCreate(['code' => 'store_admin'], ['name' => 'Admin Toko', 'level' => 50]);
        Role::firstOrCreate(['code' => 'customer'], ['name' => 'Customer', 'level' => 10]);

        // 1. Customer A
        $this->customerA = User::factory()->create([
            'name' => 'Dewi Customer A',
            'phone' => '081211111111',
            'status' => 'active',
        ]);
        UserRole::create([
            'user_id' => $this->customerA->id,
            'role_id' => Role::where('code', 'customer')->first()->id,
            'status' => 'active',
        ]);

        // 2. Customer B
        $this->customerB = User::factory()->create([
            'name' => 'Budi Customer B',
            'phone' => '081222222222',
            'status' => 'active',
        ]);
        UserRole::create([
            'user_id' => $this->customerB->id,
            'role_id' => Role::where('code', 'customer')->first()->id,
            'status' => 'active',
        ]);

        // 3. Store Owner & Store
        $this->storeOwner = User::factory()->create([
            'name' => 'Pak Bos Toko',
            'phone' => '081233333333',
            'status' => 'active',
        ]);
        UserRole::create([
            'user_id' => $this->storeOwner->id,
            'role_id' => Role::where('code', 'store_admin')->first()->id,
            'status' => 'active',
        ]);

        $this->store = Store::create([
            'owner_user_id' => $this->storeOwner->id,
            'name' => 'Berkah Laundry Express',
            'description' => 'Cuci bersih, rapi, dan wangi tahan lama.',
            'address_text' => 'Jl. Kebon Jeruk No. 12, Jakarta',
            'latitude' => -6.1950,
            'longitude' => 106.7750,
            'service_radius_km' => 5.0,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        // 4. Completed Order for Customer A
        $this->orderCompleted = Order::create([
            'order_number' => 'LK-TEST-COMPLETED-01',
            'customer_id' => $this->customerA->id,
            'store_id' => $this->store->id,
            'status' => 'COMPLETED',
            'payment_status' => 'PAID',
            'estimated_weight_kg' => 3.0,
            'actual_weight_kg' => 3.2,
            'estimated_amount' => 24000,
            'final_amount' => 25600,
            'delivery_fee' => 5000,
            'commission_amount' => 1000,
            'courier_wage' => 4000,
            'pickup_address_snapshot' => [
                'address_text' => 'Jl. Anggrek No. 1',
                'latitude' => -6.1955,
                'longitude' => 106.7755,
            ],
            'delivery_address_snapshot' => [
                'address_text' => 'Jl. Anggrek No. 1',
                'latitude' => -6.1955,
                'longitude' => 106.7755,
            ],
            'completed_at' => now(),
        ]);

        // 5. In-progress Order for Customer A
        $this->orderProcessing = Order::create([
            'order_number' => 'LK-TEST-PROCESSING-01',
            'customer_id' => $this->customerA->id,
            'store_id' => $this->store->id,
            'status' => 'PROCESSING',
            'payment_status' => 'PAID',
            'estimated_weight_kg' => 2.0,
            'actual_weight_kg' => 2.0,
            'estimated_amount' => 16000,
            'final_amount' => 16000,
            'delivery_fee' => 5000,
            'commission_amount' => 1000,
            'courier_wage' => 4000,
            'pickup_address_snapshot' => [
                'address_text' => 'Jl. Anggrek No. 1',
                'latitude' => -6.1955,
                'longitude' => 106.7755,
            ],
            'delivery_address_snapshot' => [
                'address_text' => 'Jl. Anggrek No. 1',
                'latitude' => -6.1955,
                'longitude' => 106.7755,
            ],
        ]);

        // 6. Order for Customer B
        $this->orderCustomerB = Order::create([
            'order_number' => 'LK-TEST-CUSB-01',
            'customer_id' => $this->customerB->id,
            'store_id' => $this->store->id,
            'status' => 'COMPLETED',
            'payment_status' => 'PAID',
            'estimated_weight_kg' => 1.5,
            'actual_weight_kg' => 1.5,
            'estimated_amount' => 12000,
            'final_amount' => 12000,
            'delivery_fee' => 5000,
            'commission_amount' => 1000,
            'courier_wage' => 4000,
            'pickup_address_snapshot' => [
                'address_text' => 'Jl. Melati No. 5',
                'latitude' => -6.1960,
                'longitude' => 106.7760,
            ],
            'delivery_address_snapshot' => [
                'address_text' => 'Jl. Melati No. 5',
                'latitude' => -6.1960,
                'longitude' => 106.7760,
            ],
            'completed_at' => now(),
        ]);
    }

    /**
     * Test 1: Customer can create complaint with category, description, and photos.
     */
    public function test_customer_can_create_complaint_with_category_description_and_photos(): void
    {
        $photo = UploadedFile::fake()->image('baju_rusak.jpg', 800, 600);

        $response = $this->actingAs($this->customerA, 'sanctum')
            ->post("/api/v1/orders/{$this->orderCompleted->id}/complaints", [
                'category' => 'DAMAGED_ITEM',
                'description' => 'Terdapat sobekan pada kemeja putih saat cucian diterima.',
                'photos' => [$photo],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'order_id' => $this->orderCompleted->id,
                    'customer_id' => $this->customerA->id,
                    'store_id' => $this->store->id,
                    'category' => 'DAMAGED_ITEM',
                    'status' => 'OPEN',
                ],
            ]);

        $complaintId = $response->json('data.id');
        $this->assertDatabaseHas('complaints', [
            'id' => $complaintId,
            'order_id' => $this->orderCompleted->id,
            'category' => 'DAMAGED_ITEM',
            'status' => 'OPEN',
        ]);

        $this->assertDatabaseHas('complaint_attachments', [
            'complaint_id' => $complaintId,
        ]);

        // Verify notification sent to store owner
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->storeOwner->id,
            'event_type' => 'COMPLAINT_UPDATED',
        ]);
    }

    /**
     * Test 2: Validation errors when creating complaint.
     */
    public function test_customer_cannot_create_complaint_with_invalid_data(): void
    {
        // Missing category & description too short
        $response = $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/v1/orders/{$this->orderCompleted->id}/complaints", [
                'description' => 'jelek',
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    /**
     * Test 3: Customer cannot complain on another customer's order.
     */
    public function test_customer_cannot_create_complaint_for_another_customers_order(): void
    {
        $response = $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/v1/orders/{$this->orderCustomerB->id}/complaints", [
                'category' => 'SERVICE_QUALITY',
                'description' => 'Pelayanan sangat lambat dan tidak ramah.',
            ]);

        $response->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    /**
     * Test 4: Store Admin can list complaints for their store.
     */
    public function test_store_admin_can_list_complaints_for_their_store(): void
    {
        Complaint::create([
            'order_id' => $this->orderCompleted->id,
            'customer_id' => $this->customerA->id,
            'store_id' => $this->store->id,
            'category' => 'LOST_ITEM',
            'description' => 'Satu pasang kaus kaki hilang dalam cucian.',
            'status' => 'OPEN',
        ]);

        $response = $this->actingAs($this->storeOwner, 'sanctum')
            ->getJson('/api/v1/store-admin/complaints');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data');
    }

    /**
     * Test 5: Store admin can update complaint status to IN_INVESTIGATION without resolution note.
     */
    public function test_store_admin_can_update_complaint_status_to_investigation(): void
    {
        $complaint = Complaint::create([
            'order_id' => $this->orderCompleted->id,
            'customer_id' => $this->customerA->id,
            'store_id' => $this->store->id,
            'category' => 'LATE_DELIVERY',
            'description' => 'Pengantaran terlambat lebih dari 2 hari.',
            'status' => 'OPEN',
        ]);

        $response = $this->actingAs($this->storeOwner, 'sanctum')
            ->patchJson("/api/v1/store-admin/complaints/{$complaint->id}/status", [
                'status' => 'IN_INVESTIGATION',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $complaint->id,
                    'status' => 'IN_INVESTIGATION',
                ],
            ]);

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'IN_INVESTIGATION',
        ]);
    }

    /**
     * Test 6: MANDATORY resolution note when resolving or rejecting complaint.
     */
    public function test_store_admin_must_provide_resolution_note_when_resolving_or_rejecting_complaint(): void
    {
        $complaint = Complaint::create([
            'order_id' => $this->orderCompleted->id,
            'customer_id' => $this->customerA->id,
            'store_id' => $this->store->id,
            'category' => 'DAMAGED_ITEM',
            'description' => 'Kancing baju lepas saat proses cuci.',
            'status' => 'IN_INVESTIGATION',
        ]);

        // Attempt resolving without resolution_note -> MUST FAIL (422)
        $failResponse = $this->actingAs($this->storeOwner, 'sanctum')
            ->patchJson("/api/v1/store-admin/complaints/{$complaint->id}/status", [
                'status' => 'RESOLVED',
            ]);

        $failResponse->assertStatus(422)
            ->assertJson(['success' => false]);

        // Provide resolution_note -> MUST SUCCEED
        $successResponse = $this->actingAs($this->storeOwner, 'sanctum')
            ->patchJson("/api/v1/store-admin/complaints/{$complaint->id}/status", [
                'status' => 'RESOLVED',
                'resolution_note' => 'Toko telah mengganti kancing baju dan memberikan voucher cuci gratis.',
            ]);

        $successResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $complaint->id,
                    'status' => 'RESOLVED',
                    'resolution_note' => 'Toko telah mengganti kancing baju dan memberikan voucher cuci gratis.',
                ],
            ]);

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'RESOLVED',
            'resolution_note' => 'Toko telah mengganti kancing baju dan memberikan voucher cuci gratis.',
        ]);

        // Verify customer received notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customerA->id,
            'event_type' => 'COMPLAINT_UPDATED',
        ]);
    }

    /**
     * Test 7: Non-owner cannot update store complaint status.
     */
    public function test_non_store_owner_cannot_update_complaint_status(): void
    {
        $complaint = Complaint::create([
            'order_id' => $this->orderCompleted->id,
            'customer_id' => $this->customerA->id,
            'store_id' => $this->store->id,
            'category' => 'OTHER',
            'description' => 'Aroma pewangi kurang harum.',
            'status' => 'OPEN',
        ]);

        $response = $this->actingAs($this->customerB, 'sanctum')
            ->patchJson("/api/v1/store-admin/complaints/{$complaint->id}/status", [
                'status' => 'RESOLVED',
                'resolution_note' => 'Selesai secara internal.',
            ]);

        $response->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    /**
     * Test 8: Customer can rate order ONLY after status is COMPLETED.
     */
    public function test_customer_can_rate_order_only_when_status_is_completed(): void
    {
        $response = $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/v1/orders/{$this->orderCompleted->id}/rating", [
                'score' => 5,
                'review' => 'Hasil cucian sangat rapi, wangi, dan pengantaran tepat waktu!',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'order_id' => $this->orderCompleted->id,
                    'customer_id' => $this->customerA->id,
                    'store_id' => $this->store->id,
                    'score' => 5,
                    'review' => 'Hasil cucian sangat rapi, wangi, dan pengantaran tepat waktu!',
                ],
            ]);

        $this->assertDatabaseHas('ratings', [
            'order_id' => $this->orderCompleted->id,
            'score' => 5,
        ]);

        // Notification to store owner
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->storeOwner->id,
            'event_type' => 'rating_submitted',
        ]);
    }

    /**
     * Test 9: Customer CANNOT rate order if status is not COMPLETED (e.g. PROCESSING).
     */
    public function test_customer_cannot_rate_order_if_not_completed(): void
    {
        $response = $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/v1/orders/{$this->orderProcessing->id}/rating", [
                'score' => 4,
                'review' => 'Sedang dicuci.',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_NOT_COMPLETED',
                ],
            ]);

        $this->assertDatabaseMissing('ratings', [
            'order_id' => $this->orderProcessing->id,
        ]);
    }

    /**
     * Test 10: System prevents duplicate rating for the same order (Unique constraint).
     */
    public function test_system_prevents_duplicate_rating_for_same_order(): void
    {
        // First rating succeeds
        $first = $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/v1/orders/{$this->orderCompleted->id}/rating", [
                'score' => 5,
                'review' => 'Ulasan pertama.',
            ]);

        $first->assertStatus(201);

        // Second rating for same order must return 409 conflict
        $second = $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/v1/orders/{$this->orderCompleted->id}/rating", [
                'score' => 3,
                'review' => 'Mencoba kirim rating kedua.',
            ]);

        $second->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'DUPLICATE_RATING',
                ],
            ]);

        // Only 1 rating exists in database
        $this->assertEquals(1, Rating::where('order_id', $this->orderCompleted->id)->count());
    }

    /**
     * Test 11: Public store ratings endpoint returns list and statistical breakdown.
     */
    public function test_public_store_ratings_summary_and_breakdown(): void
    {
        // Rate from customer A (5 stars)
        Rating::create([
            'order_id' => $this->orderCompleted->id,
            'customer_id' => $this->customerA->id,
            'store_id' => $this->store->id,
            'score' => 5,
            'review' => 'Bagus sekali!',
        ]);

        // Rate from customer B (4 stars)
        Rating::create([
            'order_id' => $this->orderCustomerB->id,
            'customer_id' => $this->customerB->id,
            'store_id' => $this->store->id,
            'score' => 4,
            'review' => 'Cukup puas.',
        ]);

        $response = $this->getJson("/api/v1/stores/{$this->store->id}/ratings");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'total' => 2,
                    'summary' => [
                        'average_score' => 4.5,
                        'total_ratings' => 2,
                        'breakdown' => [
                            '5' => 1,
                            '4' => 1,
                            '3' => 0,
                            '2' => 0,
                            '1' => 0,
                        ],
                    ],
                ],
            ]);
    }
}
