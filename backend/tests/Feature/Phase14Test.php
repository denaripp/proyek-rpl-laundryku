<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\CourierProfile;
use App\Models\CourierStoreAssignment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Rating;
use App\Models\Role;
use App\Models\ServiceCategory;
use App\Models\Store;
use App\Models\StoreDocument;
use App\Models\StoreLedgerEntry;
use App\Models\StoreService;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase14Test extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $storeOwnerA;
    protected Store $storeA;
    protected User $storeOwnerB;
    protected Store $storeB;
    protected User $courierUser;
    protected CourierProfile $courierProfile;
    protected User $customer;
    protected ServiceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        // Roles
        $superAdminRole = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin']);
        $storeAdminRole = Role::firstOrCreate(['code' => 'store_admin'], ['name' => 'Store Admin']);
        $courierRole = Role::firstOrCreate(['code' => 'courier'], ['name' => 'Courier']);
        $customerRole = Role::firstOrCreate(['code' => 'customer'], ['name' => 'Customer']);

        // Super Admin
        $this->superAdmin = User::factory()->create([
            'email' => 'super@laundryku.com',
            'phone' => '081100000001',
            'status' => 'active',
            'password' => Hash::make('SuperSecret123!'),
        ]);
        UserRole::create(['user_id' => $this->superAdmin->id, 'role_id' => $superAdminRole->id, 'status' => 'active']);

        // Store Owner A & Store A
        $this->storeOwnerA = User::factory()->create([
            'email' => 'admina@storea.com',
            'phone' => '081200000001',
            'status' => 'active',
            'password' => Hash::make('StorePassA123!'),
        ]);
        UserRole::create(['user_id' => $this->storeOwnerA->id, 'role_id' => $storeAdminRole->id, 'status' => 'active']);
        $this->storeA = Store::create([
            'owner_user_id' => $this->storeOwnerA->id,
            'name' => 'Laundry Berkah A',
            'address_text' => 'Jl. Mawar No. 1, Jakarta',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'service_radius_km' => 5.0,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        // Store Owner B & Store B
        $this->storeOwnerB = User::factory()->create([
            'email' => 'adminb@storeb.com',
            'phone' => '081300000002',
            'status' => 'active',
            'password' => Hash::make('StorePassB123!'),
        ]);
        UserRole::create(['user_id' => $this->storeOwnerB->id, 'role_id' => $storeAdminRole->id, 'status' => 'active']);
        $this->storeB = Store::create([
            'owner_user_id' => $this->storeOwnerB->id,
            'name' => 'Laundry Kilat B',
            'address_text' => 'Jl. Melati No. 2, Jakarta',
            'latitude' => -6.210000,
            'longitude' => 106.820000,
            'service_radius_km' => 5.0,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        // Courier
        $this->courierUser = User::factory()->create([
            'email' => 'courier@laundryku.com',
            'phone' => '081400000003',
            'status' => 'active',
            'password' => Hash::make('CourierPass123!'),
        ]);
        UserRole::create(['user_id' => $this->courierUser->id, 'role_id' => $courierRole->id, 'status' => 'active']);
        $this->courierProfile = CourierProfile::create([
            'user_id' => $this->courierUser->id,
            'vehicle_type' => 'motor',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        CourierStoreAssignment::create([
            'courier_id' => $this->courierProfile->id,
            'store_id' => $this->storeA->id,
            'recruited_by' => $this->storeOwnerA->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        // Customer
        $this->customer = User::factory()->create([
            'email' => 'customer@laundryku.com',
            'phone' => '081500000004',
            'status' => 'active',
            'password' => Hash::make('CustomerPass123!'),
        ]);
        UserRole::create(['user_id' => $this->customer->id, 'role_id' => $customerRole->id, 'status' => 'active']);

        // Category
        $this->category = ServiceCategory::create([
            'name' => 'Kiloan Reguler',
            'slug' => 'kiloan-reguler',
            'status' => 'active',
        ]);
    }

    /**
     * TEST-04: Multi-tenant Isolation Audit.
     * Ensure Store Admin B cannot view, modify, or leak Store Admin A's services, orders, couriers, complaints, ledgers, or private documents.
     */
    public function test_tenant_isolation_store_admin_b_cannot_access_store_a_resources(): void
    {
        $tokenB = $this->storeOwnerB->createToken('admin-b')->plainTextToken;

        // 1. Create a service for Store A
        $serviceA = StoreService::create([
            'store_id' => $this->storeA->id,
            'category_id' => $this->category->id,
            'name' => 'Cuci Lipat A Exclusive',
            'pricing_unit' => 'KG',
            'price_per_unit' => 10000,
            'minimum_charge' => 10000,
            'estimated_duration_hours' => 24,
            'is_active' => true,
        ]);

        // Admin B tries to update Store A's service -> Must be 403 or 404
        $updateServiceRes = $this->actingAs($this->storeOwnerB, 'sanctum')
            ->putJson("/api/v1/store-admin/services/{$serviceA->id}", [
                'name' => 'Hacked Service Name',
                'price_per_unit' => 500,
            ]);
        $this->assertTrue(in_array($updateServiceRes->status(), [403, 404]), 'Admin B must not update Store A service.');

        // 2. Create an order for Store A
        $orderA = Order::create([
            'order_number' => 'ORD-TEST-ISO-01',
            'customer_id' => $this->customer->id,
            'store_id' => $this->storeA->id,
            'pickup_address_snapshot' => ['address_text' => 'Jl. Customer No. 1', 'latitude' => -6.20, 'longitude' => 106.81],
            'delivery_address_snapshot' => ['address_text' => 'Jl. Customer No. 1', 'latitude' => -6.20, 'longitude' => 106.81],
            'status' => 'PENDING_PICKUP',
            'payment_status' => 'UNPAID',
            'estimated_amount' => 20000,
            'estimated_weight_kg' => 2.0,
        ]);

        // Admin B tries to view Store A's order detail -> Must be 403 or 404
        $orderDetailRes = $this->actingAs($this->storeOwnerB, 'sanctum')
            ->getJson("/api/v1/orders/{$orderA->id}");
        $this->assertTrue(in_array($orderDetailRes->status(), [403, 404]), 'Admin B must not view Store A order.');

        // Admin B tries to transition Store A's order -> Must be 403 or 404
        $orderTransitionRes = $this->actingAs($this->storeOwnerB, 'sanctum')
            ->postJson("/api/v1/orders/{$orderA->id}/transition", [
                'to_status' => 'PICKUP_ASSIGNED',
                'note' => 'Unauthorized cancellation attempt',
            ]);
        $this->assertTrue(in_array($orderTransitionRes->status(), [403, 404]), 'Admin B must not transition Store A order.');

        // 3. Admin B tries to access Store A's couriers
        $storeCouriersRes = $this->actingAs($this->storeOwnerB, 'sanctum')
            ->getJson("/api/v1/store-admin/couriers?store_id={$this->storeA->id}");
        $this->assertTrue(in_array($storeCouriersRes->status(), [200, 403, 404]));
        if ($storeCouriersRes->status() === 200) {
            // Must not list Store A assigned couriers in Admin B's store context
            $couriers = $storeCouriersRes->json('data.assigned_couriers') ?? [];
            $this->assertEmpty($couriers);
        }

        // 4. Admin B tries to access Store A's ledger
        $ledgerRes = $this->actingAs($this->storeOwnerB, 'sanctum')
            ->getJson("/api/v1/store-admin/ledger?store_id={$this->storeA->id}");
        $this->assertTrue(in_array($ledgerRes->status(), [200, 403, 404]));
        if ($ledgerRes->status() === 200) {
            // Must not list Store A ledger data
            $this->assertEquals(0, $ledgerRes->json('data.summary.total_balance') ?? 0);
        }

        // 5. Admin B tries to stream Store A's private documents
        $docA = StoreDocument::create([
            'store_id' => $this->storeA->id,
            'document_type' => 'nib',
            'storage_key' => 'private/store_docs/nib_store_a.pdf',
        ]);
        Storage::disk('local')->put('private/store_docs/nib_store_a.pdf', 'CONFIDENTIAL_NIB_STORE_A');

        $docStreamRes = $this->actingAs($this->storeOwnerB, 'sanctum')
            ->get("/api/v1/documents/store/{$docA->id}");
        $this->assertEquals(403, $docStreamRes->status(), 'Admin B must be forbidden (403) from streaming Store A documents.');
    }

    /**
     * TEST-05: Webhook Security, Signature, Idempotency & Out-of-order handling.
     */
    public function test_xendit_webhook_security_signature_and_idempotency(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-WH-TEST-01',
            'customer_id' => $this->customer->id,
            'store_id' => $this->storeA->id,
            'pickup_address_snapshot' => ['address_text' => 'Jl. Customer', 'latitude' => -6.2, 'longitude' => 106.8],
            'delivery_address_snapshot' => ['address_text' => 'Jl. Customer', 'latitude' => -6.2, 'longitude' => 106.8],
            'status' => 'AWAITING_PAYMENT',
            'payment_status' => 'UNPAID',
            'estimated_amount' => 50000,
            'estimated_weight_kg' => 3.0,
            'final_amount' => 50000,
            'commission_amount' => 2000,
        ]);

        $token = config('services.xendit.webhook_token', 'test_xendit_webhook_token_12345');

        $validPayload = [
            'event' => 'invoice.paid',
            'id' => 'inv_test_webhook_001',
            'external_id' => $order->id,
            'status' => 'PAID',
            'amount' => 50000,
            'payment_method' => 'QRIS',
            'payment_channel' => 'SHOPEEPAY',
            'paid_at' => now()->toIso8601String(),
        ];

        // 1. Invalid signature / token -> 401 Unauthorized
        $invalidSigRes = $this->withHeaders([
            'x-callback-token' => 'WRONG_INVALID_TOKEN',
        ])->postJson('/api/v1/webhooks/xendit', $validPayload);

        $invalidSigRes->assertStatus(401);

        // 2. Missing signature / token -> 401 Unauthorized
        $missingSigRes = $this->postJson('/api/v1/webhooks/xendit', $validPayload);
        $missingSigRes->assertStatus(401);

        // 3. Valid webhook payload -> 200 OK & transitions order to PAID
        $successRes = $this->withHeaders([
            'x-callback-token' => $token,
        ])->postJson('/api/v1/webhooks/xendit', $validPayload);

        $successRes->assertStatus(200);

        $order->refresh();
        $this->assertEquals('PAID', $order->status);
        $this->assertEquals('PAID', $order->payment_status);

        // 4. Duplicate webhook payload -> Must be idempotent (200 duplicate_ignored)
        $duplicateRes = $this->withHeaders([
            'x-callback-token' => $token,
        ])->postJson('/api/v1/webhooks/xendit', $validPayload);

        $duplicateRes->assertStatus(200);

        // Ensure only ONE payment record exists for this invoice ID
        $this->assertEquals(1, Payment::where('order_id', $order->id)->whereIn('status', ['paid', 'PAID'])->count());

        // 5. Out-of-order / Delayed webhook (e.g. order already COMPLETED)
        $order->update(['status' => 'COMPLETED']);

        $latePayload = $validPayload;
        $latePayload['id'] = 'inv_test_webhook_late_002';

        $lateRes = $this->withHeaders([
            'x-callback-token' => $token,
        ])->postJson('/api/v1/webhooks/xendit', $latePayload);

        // Delayed webhook should be handled gracefully (200)
        $this->assertEquals(200, $lateRes->status());
    }

    /**
     * TEST-06: File Upload Security & Private Document Isolation.
     */
    public function test_file_upload_security_validation_and_private_file_isolation(): void
    {
        $tokenCustomer = $this->customer->createToken('customer')->plainTextToken;

        // 1. Upload illegal PHP script as profile photo -> 422
        $illegalFile = UploadedFile::fake()->create('malicious.php', 100, 'application/x-php');
        $resIllegal = $this->withHeader('Authorization', "Bearer {$tokenCustomer}")
            ->postJson('/api/v1/profile/photo', [
                'photo' => $illegalFile,
            ]);
        $resIllegal->assertStatus(422);

        // 2. Upload oversized profile photo (> 5MB) -> 422
        $oversizedFile = UploadedFile::fake()->create('large_image.jpg', 6000, 'image/jpeg');
        $resOversized = $this->withHeader('Authorization', "Bearer {$tokenCustomer}")
            ->postJson('/api/v1/profile/photo', [
                'photo' => $oversizedFile,
            ]);
        $resOversized->assertStatus(422);

        // 3. Valid photo upload succeeds
        $validFile = UploadedFile::fake()->image('avatar.jpg', 300, 300);
        $resValid = $this->withHeader('Authorization', "Bearer {$tokenCustomer}")
            ->postJson('/api/v1/profile/photo', [
                'photo' => $validFile,
            ]);
        $resValid->assertStatus(200);

        // 4. Private files not exposed via public URL
        Storage::disk('local')->put('private/test_secret.pdf', 'CONFIDENTIAL');
        $publicAccessRes = $this->get('/storage/private/test_secret.pdf');
        $this->assertTrue(in_array($publicAccessRes->status(), [403, 404]), 'Direct public access to private disk must be blocked.');
    }

    /**
     * TEST-03: Full End-to-End Order Lifecycle.
     * Registration -> Order -> Pickup -> Weighing -> Price Approval -> Payment -> Processing -> Delivery -> Completion -> Ledger -> Rating.
     */
    public function test_full_end_to_end_order_lifecycle(): void
    {
        $customerToken = $this->customer->createToken('cust')->plainTextToken;
        $adminAToken = $this->storeOwnerA->createToken('admin-a')->plainTextToken;
        $courierToken = $this->courierUser->createToken('courier')->plainTextToken;

        // Step 1: Store Admin adds a Service
        $service = StoreService::create([
            'store_id' => $this->storeA->id,
            'category_id' => $this->category->id,
            'name' => 'Cuci Kiloan Reguler E2E',
            'pricing_unit' => 'KG',
            'price_per_unit' => 10000,
            'minimum_charge' => 10000,
            'estimated_duration_hours' => 24,
            'is_active' => true,
        ]);

        // Step 2: Customer creates Order
        $orderCreateRes = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'store_id' => $this->storeA->id,
                'pickup_address' => [
                    'address_text' => 'Jl. Mawar No. 10, Jakarta',
                    'latitude' => -6.200000,
                    'longitude' => 106.816666,
                ],
                'delivery_address' => [
                    'address_text' => 'Jl. Mawar No. 10, Jakarta',
                    'latitude' => -6.200000,
                    'longitude' => 106.816666,
                ],
                'items' => [
                    [
                        'service_id' => $service->id,
                        'quantity' => 3.0,
                    ],
                ],
            ]);

        $orderCreateRes->assertStatus(201);
        $orderId = $orderCreateRes->json('data.id');
        $this->assertNotEmpty($orderId);

        // Step 3: Store Admin assigns courier for pickup -> PICKUP_ASSIGNED
        $assignCourierRes = $this->actingAs($this->storeOwnerA, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'PICKUP_ASSIGNED',
                'courier_id' => $this->courierUser->id,
                'note' => 'Kurir ditugaskan untuk pickup.',
            ]);
        $assignCourierRes->assertStatus(200);

        // Step 4: Courier accepts and completes pickup -> PICKED_UP -> AT_STORE
        $pickupRes = $this->actingAs($this->courierUser, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'PICKED_UP',
                'note' => 'Cucian berhasil diambil dari customer.',
            ]);
        $pickupRes->assertStatus(200);

        $arrivedRes = $this->actingAs($this->courierUser, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'AT_STORE',
                'note' => 'Cucian tiba di outlet laundry.',
            ]);
        $arrivedRes->assertStatus(200);

        // Step 5: Store Admin weighs laundry and sets final weight (3.5 KG)
        $orderItem = OrderItem::where('order_id', $orderId)->first();
        $weighRes = $this->actingAs($this->storeOwnerA, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'WEIGHED',
                'actual_weight_kg' => 3.5,
                'items' => [
                    [
                        'item_id' => $orderItem->id,
                        'quantity_actual' => 3.5,
                    ],
                ],
                'note' => 'Ditimbang 3.5 kg bersih',
            ]);
        $weighRes->assertStatus(200);

        // Store Admin transitions to AWAITING_PRICE_APPROVAL
        $awaitApprovalRes = $this->actingAs($this->storeOwnerA, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'AWAITING_PRICE_APPROVAL',
                'note' => 'Timbangan selesai: 3.5 KG. Menunggu persetujuan harga customer.',
            ]);
        $awaitApprovalRes->assertStatus(200);

        // Step 6: Customer approves the final price -> Moves to AWAITING_PAYMENT
        $approvePriceRes = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/price-approval", [
                'action' => 'approve',
            ]);
        $approvePriceRes->assertStatus(200);

        // Step 7: Payment via simulated Xendit Webhook -> PAID
        $webhookToken = config('services.xendit.webhook_token', 'test_xendit_webhook_token_12345');
        $payWebhookRes = $this->withHeaders([
            'x-callback-token' => $webhookToken,
        ])->postJson('/api/v1/webhooks/xendit', [
            'event' => 'invoice.paid',
            'id' => 'inv_e2e_test_999',
            'external_id' => $orderId,
            'status' => 'PAID',
            'amount' => 35000,
            'payment_method' => 'QRIS',
            'paid_at' => now()->toIso8601String(),
        ]);
        $payWebhookRes->assertStatus(200);

        // Step 8: Store Admin processes laundry: PROCESSING -> READY_FOR_DELIVERY
        $washRes = $this->actingAs($this->storeOwnerA, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'PROCESSING',
                'note' => 'Pakaian sedang dicuci.',
            ]);
        $washRes->assertStatus(200);

        $readyRes = $this->actingAs($this->storeOwnerA, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'READY_FOR_DELIVERY',
                'note' => 'Pakaian siap diantar kembali.',
            ]);
        $readyRes->assertStatus(200);

        // Step 9: Store assigns Courier for Delivery -> DELIVERY_ASSIGNED
        $assignDelivRes = $this->actingAs($this->storeOwnerA, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'DELIVERY_ASSIGNED',
                'courier_id' => $this->courierUser->id,
                'note' => 'Kurir ditugaskan untuk pengantaran.',
            ]);
        $assignDelivRes->assertStatus(200);

        // Step 10: Courier delivers order: OUT_FOR_DELIVERY -> COMPLETED
        $outRes = $this->actingAs($this->courierUser, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'OUT_FOR_DELIVERY',
                'note' => 'Kurir sedang dalam perjalanan mengantar cucian.',
            ]);
        $outRes->assertStatus(200);

        $completeRes = $this->actingAs($this->courierUser, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'COMPLETED',
                'note' => 'Cucian telah sampai di tangan customer.',
            ]);
        $completeRes->assertStatus(200);

        // Step 11: Verify Ledger entries created for store
        $this->assertDatabaseHas('store_ledger_entries', [
            'store_id' => $this->storeA->id,
            'order_id' => $orderId,
            'entry_type' => 'PAYMENT_RECEIPT',
        ]);

        // Step 12: Customer submits 5-star Rating
        $ratingRes = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/rating", [
                'score' => 5,
                'review' => 'Pelayanan cepat dan cucian sangat wangi!',
            ]);
        $ratingRes->assertStatus(201)
            ->assertJsonPath('data.score', 5);

        // Verify Rating is recorded in database
        $this->assertDatabaseHas('ratings', [
            'order_id' => $orderId,
            'customer_id' => $this->customer->id,
            'store_id' => $this->storeA->id,
            'score' => 5,
        ]);

        // Verify Store ratings endpoint reflects average rating
        $storeRatingsRes = $this->getJson("/api/v1/stores/{$this->storeA->id}/ratings");
        $storeRatingsRes->assertStatus(200)
            ->assertJsonPath('meta.summary.average_score', 5)
            ->assertJsonPath('meta.summary.total_ratings', 1);
    }

    /**
     * NFR-SEC-01: Security Headers on HTTP Responses.
     */
    public function test_security_headers_present_on_api_responses(): void
    {
        $response = $this->getJson('/api/v1/service-categories');

        $response->assertStatus(200);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /**
     * NFR-SEC-02: Password Hashing Verification.
     */
    public function test_passwords_are_securely_hashed(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('MySecurePassword123!'),
        ]);

        $this->assertNotEquals('MySecurePassword123!', $user->password);
        $this->assertTrue(Hash::check('MySecurePassword123!', $user->password));
    }
}
