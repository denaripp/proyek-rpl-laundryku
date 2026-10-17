<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Role;
use App\Models\ServiceCategory;
use App\Models\Store;
use App\Models\StoreService;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5Test extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected string $customerToken;

    protected User $storeOwner;
    protected string $storeOwnerToken;
    protected Store $store;

    protected User $courier;
    protected string $courierToken;

    protected User $otherCustomer;
    protected string $otherCustomerToken;

    protected StoreService $serviceKiloan;
    protected StoreService $serviceSatuan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $customerRole = Role::where('code', 'customer')->first();
        $storeAdminRole = Role::where('code', 'store_admin')->first();
        $courierRole = Role::where('code', 'courier')->first();

        // 1. Customer (Dago Bandung: -6.8850, 107.6140)
        $this->customer = User::create([
            'name' => 'Budi Customer',
            'email' => 'customer.test@example.com',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create([
            'user_id' => $this->customer->id,
            'address_text' => 'Jl. Dago Asri No. 12, Bandung',
            'latitude' => -6.8850,
            'longitude' => 107.6140,
        ]);
        UserRole::create([
            'user_id' => $this->customer->id,
            'role_id' => $customerRole->id,
            'status' => 'active',
        ]);
        $this->customerToken = $this->customer->createToken('test')->plainTextToken;

        // 2. Other Customer
        $this->otherCustomer = User::create([
            'name' => 'Siti Customer',
            'email' => 'other.customer@example.com',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create([
            'user_id' => $this->otherCustomer->id,
            'address_text' => 'Jl. Buah Batu No. 50, Bandung',
            'latitude' => -6.9500,
            'longitude' => 107.6300,
        ]);
        UserRole::create([
            'user_id' => $this->otherCustomer->id,
            'role_id' => $customerRole->id,
            'status' => 'active',
        ]);
        $this->otherCustomerToken = $this->otherCustomer->createToken('test')->plainTextToken;

        // 3. Store Owner & Store (Dago: -6.8885, 107.6150, radius 5km)
        $this->storeOwner = User::create([
            'name' => 'Rian Barokah',
            'email' => 'owner.test@example.com',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create([
            'user_id' => $this->storeOwner->id,
        ]);
        $this->storeOwnerToken = $this->storeOwner->createToken('test')->plainTextToken;

        $this->store = Store::create([
            'owner_user_id' => $this->storeOwner->id,
            'name' => 'Laundry Barokah Test',
            'address_text' => 'Jl. Ir. H. Juanda No. 88, Bandung',
            'latitude' => -6.8885,
            'longitude' => 107.6150,
            'service_radius_km' => 5.0,
            'approval_status' => 'approved',
            'operational_status' => 'active',
            'approved_at' => now(),
        ]);

        UserRole::create([
            'user_id' => $this->storeOwner->id,
            'role_id' => $storeAdminRole->id,
            'store_id' => $this->store->id,
            'status' => 'active',
        ]);

        // 4. Courier
        $this->courier = User::create([
            'name' => 'Doni Kurir',
            'email' => 'courier.test@example.com',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create([
            'user_id' => $this->courier->id,
        ]);
        UserRole::create([
            'user_id' => $this->courier->id,
            'role_id' => $courierRole->id,
            'status' => 'active',
        ]);
        $this->courierToken = $this->courier->createToken('test')->plainTextToken;

        // 5. Services
        $catKiloan = ServiceCategory::create(['name' => 'Kiloan', 'slug' => 'kiloan', 'status' => 'active']);
        $catSatuan = ServiceCategory::create(['name' => 'Satuan', 'slug' => 'satuan', 'status' => 'active']);

        $this->serviceKiloan = StoreService::create([
            'store_id' => $this->store->id,
            'category_id' => $catKiloan->id,
            'name' => 'Cuci Komplit Reguler',
            'pricing_unit' => 'kg',
            'price_per_unit' => 7000,
            'minimum_charge' => 21000, // min 3kg
            'estimated_duration_hours' => 48,
            'is_active' => true,
        ]);

        $this->serviceSatuan = StoreService::create([
            'store_id' => $this->store->id,
            'category_id' => $catSatuan->id,
            'name' => 'Jas / Blazer Satuan',
            'pricing_unit' => 'item',
            'price_per_unit' => 25000,
            'minimum_charge' => 0,
            'estimated_duration_hours' => 24,
            'is_active' => true,
        ]);
    }

    public function test_customer_can_create_order_with_snapshot()
    {
        $payload = [
            'store_id' => $this->store->id,
            'pickup_address' => [
                'address_text' => 'Jl. Dago Asri No. 12, Bandung',
                'latitude' => -6.8850,
                'longitude' => 107.6140,
                'label' => 'Rumah',
            ],
            'delivery_address' => [
                'address_text' => 'Jl. Dago Asri No. 12, Bandung',
                'latitude' => -6.8850,
                'longitude' => 107.6140,
                'label' => 'Rumah',
            ],
            'customer_notes' => 'Jangan pakai pewangi mawar',
            'items' => [
                [
                    'service_id' => $this->serviceKiloan->id,
                    'quantity' => 2.0, // below min charge 21,000 -> line_estimated_amount should apply min charge 21,000
                ],
                [
                    'service_id' => $this->serviceSatuan->id,
                    'quantity' => 2.0, // 2 * 25,000 = 50,000
                ],
            ],
        ];

        $response = $this->withToken($this->customerToken)
            ->postJson('/api/v1/orders', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.store_id', $this->store->id)
            ->assertJsonPath('data.status', 'PENDING_PICKUP')
            ->assertJsonPath('data.estimated_amount', 71000); // 21000 + 50000

        $orderId = $response->json('data.id');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => 'PENDING_PICKUP',
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'service_name_snapshot' => 'Cuci Komplit Reguler',
            'pricing_unit_snapshot' => 'kg',
            'unit_price_snapshot' => 7000,
            'minimum_charge_snapshot' => 21000,
        ]);

        // Verify initial history
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $orderId,
            'from_status' => null,
            'to_status' => 'PENDING_PICKUP',
            'actor_user_id' => $this->customer->id,
        ]);
    }

    public function test_order_creation_rejects_customer_outside_store_radius()
    {
        // Far location: Jakarta (-6.2000, 106.8166) ~120km away
        $payload = [
            'store_id' => $this->store->id,
            'pickup_address' => [
                'address_text' => 'Jl. Sudirman No. 1, Jakarta',
                'latitude' => -6.2000,
                'longitude' => 106.8166,
            ],
            'items' => [
                [
                    'service_id' => $this->serviceKiloan->id,
                    'quantity' => 5.0,
                ],
            ],
        ];

        $response = $this->withToken($this->customerToken)
            ->postJson('/api/v1/orders', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'STORE_OUT_OF_RADIUS');
    }

    public function test_snapshots_are_isolated_from_subsequent_service_price_changes()
    {
        // 1. Create order with price 7000
        $payload = [
            'store_id' => $this->store->id,
            'pickup_address' => [
                'address_text' => 'Jl. Dago Asri No. 12',
                'latitude' => -6.8850,
                'longitude' => 107.6140,
            ],
            'items' => [
                [
                    'service_id' => $this->serviceKiloan->id,
                    'quantity' => 4.0, // 4 * 7000 = 28000
                ],
            ],
        ];

        $res = $this->withToken($this->customerToken)
            ->postJson('/api/v1/orders', $payload);

        $orderId = $res->json('data.id');

        // 2. Change store service price to 15,000
        $this->serviceKiloan->update(['price_per_unit' => 15000]);

        // 3. Check order details still has original snapshot unit_price 7000
        $detailRes = $this->withToken($this->customerToken)
            ->getJson('/api/v1/orders/' . $orderId);

        $detailRes->assertStatus(200)
            ->assertJsonPath('data.items.0.unit_price', 7000)
            ->assertJsonPath('data.estimated_amount', 28000);
    }

    public function test_complete_state_machine_happy_path()
    {
        // 1. Customer creates order (PENDING_PICKUP)
        $orderRes = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'store_id' => $this->store->id,
                'pickup_address' => [
                    'address_text' => 'Jl. Dago Asri No. 12',
                    'latitude' => -6.8850,
                    'longitude' => 107.6140,
                ],
                'items' => [
                    [
                        'service_id' => $this->serviceKiloan->id,
                        'quantity' => 3.0,
                    ],
                ],
            ]);
        $orderRes->assertStatus(201);
        $orderId = $orderRes->json('data.id');

        // 2. Store Admin assigns Courier -> PICKUP_ASSIGNED
        $res = $this->actingAs($this->storeOwner, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'PICKUP_ASSIGNED',
                'courier_id' => $this->courier->id,
                'note' => 'Tugaskan kurir Doni untuk jemput',
            ]);
        $res->assertStatus(200)->assertJsonPath('data.status', 'PICKUP_ASSIGNED');
        $this->assertDatabaseHas('order_assignments', [
            'order_id' => $orderId,
            'courier_id' => $this->courier->id,
            'task_type' => 'pickup',
            'status' => 'assigned',
        ]);

        // 3. Courier picks up laundry -> PICKED_UP
        $res = $this->actingAs($this->courier, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'PICKED_UP',
                'note' => 'Sudah diambil dari customer',
            ]);
        $res->assertStatus(200)->assertJsonPath('data.status', 'PICKED_UP');

        // 4. Courier delivers to store -> AT_STORE
        $res = $this->actingAs($this->courier, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'AT_STORE',
                'note' => 'Cucian sudah sampai di outlet',
            ]);
        $res->assertStatus(200)->assertJsonPath('data.status', 'AT_STORE');

        // 5. Store Admin weighs laundry -> WEIGHED (actual_weight_kg = 4.5kg -> 4.5 * 7000 = 31500)
        $orderItem = OrderItem::where('order_id', $orderId)->first();
        $res = $this->actingAs($this->storeOwner, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'WEIGHED',
                'actual_weight_kg' => 4.5,
                'items' => [
                    [
                        'id' => $orderItem->id,
                        'actual_quantity' => 4.5,
                    ],
                ],
                'note' => 'Ditimbang 4.5 kg bersih',
            ]);
        $res->assertStatus(200)
            ->assertJsonPath('data.status', 'WEIGHED')
            ->assertJsonPath('data.actual_weight_kg', 4.5)
            ->assertJsonPath('data.final_amount', 31500);

        // 6. Store Admin sends to customer -> AWAITING_PRICE_APPROVAL
        $res = $this->actingAs($this->storeOwner, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'AWAITING_PRICE_APPROVAL',
                'note' => 'Menunggu konfirmasi harga dari customer',
            ]);
        $res->assertStatus(200)->assertJsonPath('data.status', 'AWAITING_PRICE_APPROVAL');

        // 7. Customer approves price -> PRICE_APPROVED
        $res = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'PRICE_APPROVED',
                'note' => 'Saya setuju dengan total Rp 31.500',
            ]);
        $res->assertStatus(200)->assertJsonPath('data.status', 'PRICE_APPROVED');

        // 8. Order moves to payment pending -> AWAITING_PAYMENT
        $res = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'AWAITING_PAYMENT',
                'note' => 'Memilih metode pembayaran',
            ]);
        $res->assertStatus(200)->assertJsonPath('data.status', 'AWAITING_PAYMENT');

        // 9. Payment confirmed -> PAID
        $res = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'PAID',
                'payment_method' => 'qris',
                'note' => 'Pembayaran lunas via QRIS',
            ]);
        $res->assertStatus(200)
            ->assertJsonPath('data.status', 'PAID')
            ->assertJsonPath('data.payment_status', 'PAID');

        // 10. Store Admin starts washing -> PROCESSING
        $res = $this->actingAs($this->storeOwner, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'PROCESSING',
                'note' => 'Cucian sedang dicuci dan dikeringkan',
            ]);
        $res->assertStatus(200)->assertJsonPath('data.status', 'PROCESSING');

        // 11. Store Admin finishes laundry -> READY_FOR_DELIVERY
        $res = $this->actingAs($this->storeOwner, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'READY_FOR_DELIVERY',
                'note' => 'Cucian sudah bersih dan dipacking rapi',
            ]);
        $res->assertStatus(200)->assertJsonPath('data.status', 'READY_FOR_DELIVERY');

        // 12. Store Admin assigns delivery courier -> DELIVERY_ASSIGNED
        $res = $this->actingAs($this->storeOwner, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'DELIVERY_ASSIGNED',
                'courier_id' => $this->courier->id,
                'note' => 'Kurir Doni ditugaskan untuk antar',
            ]);
        $res->assertStatus(200)->assertJsonPath('data.status', 'DELIVERY_ASSIGNED');

        // 13. Courier is on the way -> OUT_FOR_DELIVERY
        $res = $this->actingAs($this->courier, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'OUT_FOR_DELIVERY',
                'note' => 'Sedang menuju rumah customer',
            ]);
        $res->assertStatus(200)->assertJsonPath('data.status', 'OUT_FOR_DELIVERY');

        // 14. Completed -> COMPLETED
        $res = $this->actingAs($this->courier, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/transition", [
                'to_status' => 'COMPLETED',
                'note' => 'Cucian telah diserahkan dan diterima oleh customer',
            ]);
        $res->assertStatus(200)->assertJsonPath('data.status', 'COMPLETED');

        // Verify total histories recorded
        $histories = OrderStatusHistory::where('order_id', $orderId)->orderBy('created_at')->get();
        $this->assertGreaterThanOrEqual(13, $histories->count());
        $this->assertEquals('COMPLETED', $histories->last()->to_status);
    }

    public function test_customer_can_cancel_strictly_at_awaiting_price_approval()
    {
        // 1. Create and advance order to AWAITING_PRICE_APPROVAL
        $order = Order::create([
            'order_number' => 'ORD-TEST-0001',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'pickup_address_snapshot' => ['address_text' => 'Jl. Dago Asri No. 12', 'latitude' => -6.8850, 'longitude' => 107.6140],
            'delivery_address_snapshot' => ['address_text' => 'Jl. Dago Asri No. 12', 'latitude' => -6.8850, 'longitude' => 107.6140],
            'status' => 'AWAITING_PRICE_APPROVAL',
            'estimated_amount' => 21000,
            'final_amount' => 50000,
            'payment_status' => 'UNPAID',
        ]);

        // 2. Customer calls cancel endpoint
        $res = $this->withToken($this->customerToken)
            ->postJson("/api/v1/orders/{$order->id}/cancel", [
                'reason' => 'Harga final melebihi perkiraan anggaran saya',
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('data.status', 'CANCELLED');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'CANCELLED',
        ]);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => 'AWAITING_PRICE_APPROVAL',
            'to_status' => 'CANCELLED',
            'actor_user_id' => $this->customer->id,
        ]);
    }

    public function test_customer_cannot_cancel_at_pending_pickup_or_processing_returns_409()
    {
        // 1. Order at PENDING_PICKUP
        $order = Order::create([
            'order_number' => 'ORD-TEST-0002',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'pickup_address_snapshot' => ['address_text' => 'Jl. Dago Asri No. 12', 'latitude' => -6.8850, 'longitude' => 107.6140],
            'delivery_address_snapshot' => ['address_text' => 'Jl. Dago Asri No. 12', 'latitude' => -6.8850, 'longitude' => 107.6140],
            'status' => 'PENDING_PICKUP',
            'estimated_amount' => 21000,
            'payment_status' => 'UNPAID',
        ]);

        $res = $this->withToken($this->customerToken)
            ->postJson("/api/v1/orders/{$order->id}/cancel", [
                'reason' => 'Mau batal saja',
            ]);

        $res->assertStatus(409)
            ->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');

        // 2. Order at PROCESSING
        $order->update(['status' => 'PROCESSING']);

        $res2 = $this->withToken($this->customerToken)
            ->postJson("/api/v1/orders/{$order->id}/cancel", [
                'reason' => 'Batal saat cuci',
            ]);

        $res2->assertStatus(409)
            ->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
    }

    public function test_invalid_state_transition_skipping_steps_returns_409()
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-0003',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'pickup_address_snapshot' => ['address_text' => 'Jl. Dago Asri No. 12', 'latitude' => -6.8850, 'longitude' => 107.6140],
            'delivery_address_snapshot' => ['address_text' => 'Jl. Dago Asri No. 12', 'latitude' => -6.8850, 'longitude' => 107.6140],
            'status' => 'PENDING_PICKUP',
            'estimated_amount' => 21000,
            'payment_status' => 'UNPAID',
        ]);

        // Try jumping straight from PENDING_PICKUP to COMPLETED
        $res = $this->withToken($this->storeOwnerToken)
            ->postJson("/api/v1/orders/{$order->id}/transition", [
                'to_status' => 'COMPLETED',
            ]);

        $res->assertStatus(409)
            ->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
    }

    public function test_unauthorized_role_transition_returns_409()
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-0004',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'pickup_address_snapshot' => ['address_text' => 'Jl. Dago Asri No. 12', 'latitude' => -6.8850, 'longitude' => 107.6140],
            'delivery_address_snapshot' => ['address_text' => 'Jl. Dago Asri No. 12', 'latitude' => -6.8850, 'longitude' => 107.6140],
            'status' => 'PAID',
            'estimated_amount' => 21000,
            'payment_status' => 'PAID',
        ]);

        // Customer attempts to transition order to PROCESSING (only Store Admin is authorized)
        $res = $this->withToken($this->customerToken)
            ->postJson("/api/v1/orders/{$order->id}/transition", [
                'to_status' => 'PROCESSING',
            ]);

        $res->assertStatus(409)
            ->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
    }

    public function test_order_tracking_endpoint_returns_event_timeline()
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-TRACK-01',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'pickup_address_snapshot' => ['address_text' => 'Jl. Dago Asri No. 12', 'latitude' => -6.8850, 'longitude' => 107.6140],
            'delivery_address_snapshot' => ['address_text' => 'Jl. Dago Asri No. 12', 'latitude' => -6.8850, 'longitude' => 107.6140],
            'status' => 'PENDING_PICKUP',
            'estimated_amount' => 21000,
            'payment_status' => 'UNPAID',
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => 'PENDING_PICKUP',
            'actor_user_id' => $this->customer->id,
            'actor_role' => 'customer',
            'note' => 'Pesanan baru dibuat',
        ]);

        $res = $this->withToken($this->customerToken)
            ->getJson("/api/v1/orders/{$order->id}/tracking");

        $res->assertStatus(200)
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.current_status', 'PENDING_PICKUP')
            ->assertJsonStructure([
                'data' => [
                    'order_id',
                    'order_number',
                    'current_status',
                    'timeline' => [
                        '*' => [
                            'from_status',
                            'to_status',
                            'note',
                            'actor_name',
                            'timestamp',
                        ],
                    ],
                ],
            ]);
    }

    public function test_other_customer_cannot_view_order_details()
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-AUTH-01',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'pickup_address_snapshot' => ['address_text' => 'Jl. Dago Asri No. 12', 'latitude' => -6.8850, 'longitude' => 107.6140],
            'delivery_address_snapshot' => ['address_text' => 'Jl. Dago Asri No. 12', 'latitude' => -6.8850, 'longitude' => 107.6140],
            'status' => 'PENDING_PICKUP',
            'estimated_amount' => 21000,
            'payment_status' => 'UNPAID',
        ]);

        $res = $this->withToken($this->otherCustomerToken)
            ->getJson("/api/v1/orders/{$order->id}");

        $res->assertStatus(403);
    }
}
