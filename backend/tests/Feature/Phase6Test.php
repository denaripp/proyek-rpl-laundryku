<?php

namespace Tests\Feature;

use App\Models\CourierProfile;
use App\Models\CourierStoreAssignment;
use App\Models\Order;
use App\Models\Role;
use App\Models\ServiceCategory;
use App\Models\Store;
use App\Models\StoreService;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6Test extends TestCase
{
    use RefreshDatabase;

    protected User $storeOwner;
    protected Store $store;
    protected StoreService $service;
    protected User $courierUser1;
    protected CourierProfile $courierProfile1;
    protected User $courierUser2;
    protected CourierProfile $courierProfile2;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic roles
        Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'level' => 100]);
        Role::firstOrCreate(['code' => 'courier'], ['name' => 'Kurir', 'level' => 30]);
        Role::firstOrCreate(['code' => 'store_admin'], ['name' => 'Admin Toko', 'level' => 50]);
        Role::firstOrCreate(['code' => 'customer'], ['name' => 'Customer', 'level' => 10]);

        // Create Store Owner & Store
        $this->storeOwner = User::factory()->create([
            'phone' => '081211111111',
            'status' => 'active',
        ]);
        UserRole::create([
            'user_id' => $this->storeOwner->id,
            'role_id' => Role::where('code', 'store_admin')->first()->id,
            'status' => 'active',
        ]);

        $this->store = Store::create([
            'owner_user_id' => $this->storeOwner->id,
            'name' => 'Laundry Bersih Kilat',
            'address_text' => 'Jl. Dago No. 100, Bandung',
            'latitude' => -6.8900,
            'longitude' => 107.6100,
            'service_radius_km' => 5.0,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        $category = ServiceCategory::create([
            'name' => 'Cuci Kiloan',
            'slug' => 'cuci-kiloan',
            'status' => 'active',
        ]);

        $this->service = StoreService::create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'name' => 'Cuci Kering Lipat Reguler',
            'pricing_unit' => 'kg',
            'price_per_unit' => 10000,
            'minimum_charge' => 20000,
            'estimated_duration_hours' => 24,
            'is_active' => true,
        ]);

        // Create Courier 1
        $this->courierUser1 = User::factory()->create([
            'name' => 'Budi Kurir 1',
            'phone' => '081222222222',
            'status' => 'active',
        ]);
        $this->courierProfile1 = CourierProfile::create([
            'user_id' => $this->courierUser1->id,
            'identity_encrypted' => '3273010101900001',
            'vehicle_type' => 'motor',
            'vehicle_plate_encrypted' => 'D 1234 ABC',
            'verification_status' => 'pending',
            'status' => 'inactive',
        ]);
        UserRole::create([
            'user_id' => $this->courierUser1->id,
            'role_id' => Role::where('code', 'courier')->first()->id,
            'status' => 'pending',
        ]);

        // Create Courier 2
        $this->courierUser2 = User::factory()->create([
            'name' => 'Siti Kurir 2',
            'phone' => '081233333333',
            'status' => 'active',
        ]);
        $this->courierProfile2 = CourierProfile::create([
            'user_id' => $this->courierUser2->id,
            'identity_encrypted' => '3273010101900002',
            'vehicle_type' => 'motor',
            'vehicle_plate_encrypted' => 'D 5678 XYZ',
            'verification_status' => 'pending',
            'status' => 'inactive',
        ]);
        UserRole::create([
            'user_id' => $this->courierUser2->id,
            'role_id' => Role::where('code', 'courier')->first()->id,
            'status' => 'pending',
        ]);

        // Create Customer
        $this->customer = User::factory()->create([
            'name' => 'Rina Customer',
            'phone' => '081244444444',
            'status' => 'active',
        ]);
        UserRole::create([
            'user_id' => $this->customer->id,
            'role_id' => Role::where('code', 'customer')->first()->id,
            'status' => 'active',
        ]);
    }

    /**
     * Helper to create a standard order.
     */
    protected function createStandardOrder(string $status = 'PENDING_PICKUP', ?string $courierId = null): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'courier_id' => $courierId,
            'pickup_address_snapshot' => [
                'label' => 'Rumah Customer',
                'address_text' => 'Jl. Ir H Juanda No. 12, Bandung',
                'latitude' => -6.8920,
                'longitude' => 107.6110,
                'recipient_name' => $this->customer->name,
                'recipient_phone' => $this->customer->phone,
            ],
            'delivery_address_snapshot' => [
                'label' => 'Rumah Customer',
                'address_text' => 'Jl. Ir H Juanda No. 12, Bandung',
                'latitude' => -6.8920,
                'longitude' => 107.6110,
                'recipient_name' => $this->customer->name,
                'recipient_phone' => $this->customer->phone,
            ],
            'status' => $status,
            'payment_status' => in_array($status, ['PAID', 'PROCESSING', 'READY_FOR_DELIVERY', 'DELIVERY_ASSIGNED', 'OUT_FOR_DELIVERY', 'COMPLETED']) ? 'PAID' : 'UNPAID',
            'estimated_weight_kg' => 3.0,
            'actual_weight_kg' => in_array($status, ['WEIGHED', 'AWAITING_PRICE_APPROVAL', 'PRICE_APPROVED', 'AWAITING_PAYMENT', 'PAID', 'PROCESSING', 'READY_FOR_DELIVERY', 'DELIVERY_ASSIGNED', 'OUT_FOR_DELIVERY', 'COMPLETED']) ? 3.0 : null,
            'estimated_amount' => 30000,
            'final_amount' => in_array($status, ['WEIGHED', 'AWAITING_PRICE_APPROVAL', 'PRICE_APPROVED', 'AWAITING_PAYMENT', 'PAID', 'PROCESSING', 'READY_FOR_DELIVERY', 'DELIVERY_ASSIGNED', 'OUT_FOR_DELIVERY', 'COMPLETED']) ? 30000 : null,
            'delivery_fee' => 0,
            'commission_amount' => 1000,
        ]);

        return $order;
    }

    /**
     * Test 1: Store Admin can list and recruit a courier candidate.
     */
    public function test_store_admin_can_list_and_recruit_courier_to_store(): void
    {
        // 1. List couriers & candidates
        $response = $this->actingAs($this->storeOwner)
            ->getJson('/api/v1/store-admin/couriers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'store',
                    'assigned_couriers',
                    'available_candidates',
                ],
            ]);

        // 2. Recruit Courier 1
        $recruitRes = $this->actingAs($this->storeOwner)
            ->postJson("/api/v1/store-admin/couriers/recruit/{$this->courierProfile1->id}");

        $recruitRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.courier_name', 'Budi Kurir 1');

        $this->assertDatabaseHas('courier_store_assignments', [
            'courier_id' => $this->courierProfile1->id,
            'store_id' => $this->store->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('courier_profiles', [
            'id' => $this->courierProfile1->id,
            'verification_status' => 'approved',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $this->courierUser1->id,
            'store_id' => $this->store->id,
            'status' => 'active',
        ]);
    }

    /**
     * Test 2: Constraint "One Courier One Store" prevents courier from being recruited to a second store.
     */
    public function test_courier_single_store_constraint_prevents_multi_store_recruitment(): void
    {
        // Recruit Courier 1 to Store 1
        CourierStoreAssignment::create([
            'courier_id' => $this->courierProfile1->id,
            'store_id' => $this->store->id,
            'recruited_by' => $this->storeOwner->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        // Create a Second Store
        $owner2 = User::factory()->create(['status' => 'active']);
        $store2 = Store::create([
            'owner_user_id' => $owner2->id,
            'name' => 'Laundry Express Toko 2',
            'address_text' => 'Jl. Riau No. 20',
            'service_radius_km' => 5.0,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        // Second Store tries to recruit Courier 1 -> MUST BE REJECTED with 422
        $response = $this->actingAs($owner2)
            ->postJson("/api/v1/store-admin/couriers/recruit/{$this->courierProfile1->id}");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'COURIER_ALREADY_ASSIGNED');
    }

    /**
     * Test 3: Store Admin can terminate courier assignment.
     */
    public function test_store_admin_can_terminate_courier_assignment(): void
    {
        $assignment = CourierStoreAssignment::create([
            'courier_id' => $this->courierProfile1->id,
            'store_id' => $this->store->id,
            'recruited_by' => $this->storeOwner->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->storeOwner)
            ->postJson("/api/v1/store-admin/couriers/terminate/{$assignment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('courier_store_assignments', [
            'id' => $assignment->id,
            'status' => 'inactive',
        ]);
    }

    /**
     * Test 4: Courier can only view tasks from their active store (FR-COURIER-03).
     */
    public function test_courier_can_only_view_tasks_for_their_active_store(): void
    {
        // Bind Courier 1 to Store 1
        CourierStoreAssignment::create([
            'courier_id' => $this->courierProfile1->id,
            'store_id' => $this->store->id,
            'recruited_by' => $this->storeOwner->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        // Create an order in Store 1
        $order1 = $this->createStandardOrder('PENDING_PICKUP');

        // Create an order in another store
        $store2 = Store::create([
            'owner_user_id' => $this->storeOwner->id,
            'name' => 'Toko Laundry Lain',
            'address_text' => 'Jl. Lain No. 99',
            'service_radius_km' => 5.0,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);
        $order2 = Order::create([
            'order_number' => 'ORD-OTHER-001',
            'customer_id' => $this->customer->id,
            'store_id' => $store2->id,
            'pickup_address_snapshot' => ['address_text' => 'Alamat'],
            'delivery_address_snapshot' => ['address_text' => 'Alamat'],
            'status' => 'PENDING_PICKUP',
            'estimated_amount' => 20000,
        ]);

        $response = $this->actingAs($this->courierUser1)
            ->getJson('/api/v1/courier/tasks?tab=available');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $orderIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($order1->id, $orderIds);
        $this->assertNotContains($order2->id, $orderIds); // Store 2 order is NOT visible
    }

    /**
     * Test 5: Courier can atomically claim available pickup task.
     */
    public function test_courier_can_atomically_claim_pickup_task(): void
    {
        // Bind Courier 1 to Store
        CourierStoreAssignment::create([
            'courier_id' => $this->courierProfile1->id,
            'store_id' => $this->store->id,
            'recruited_by' => $this->storeOwner->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $order = $this->createStandardOrder('PENDING_PICKUP');

        $response = $this->actingAs($this->courierUser1)
            ->postJson("/api/v1/courier/tasks/{$order->id}/claim");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'PICKUP_ASSIGNED')
            ->assertJsonPath('data.courier_id', $this->courierUser1->id);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'PICKUP_ASSIGNED',
            'courier_id' => $this->courierUser1->id,
        ]);

        $this->assertDatabaseHas('order_assignments', [
            'order_id' => $order->id,
            'courier_id' => $this->courierUser1->id,
            'task_type' => 'pickup',
            'status' => 'assigned',
        ]);
    }

    /**
     * Test 6: Atomic claiming prevents a second courier from taking the same task (FR-COURIER-05).
     */
    public function test_atomic_claim_prevents_two_couriers_from_claiming_same_task(): void
    {
        // Bind both Courier 1 & 2 to Store
        CourierStoreAssignment::create([
            'courier_id' => $this->courierProfile1->id,
            'store_id' => $this->store->id,
            'recruited_by' => $this->storeOwner->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        CourierStoreAssignment::create([
            'courier_id' => $this->courierProfile2->id,
            'store_id' => $this->store->id,
            'recruited_by' => $this->storeOwner->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $order = $this->createStandardOrder('PENDING_PICKUP');

        // Courier 1 claims first -> SUCCEEDS
        $res1 = $this->actingAs($this->courierUser1)
            ->postJson("/api/v1/courier/tasks/{$order->id}/claim");
        $res1->assertStatus(200);

        // Courier 2 tries to claim same order -> MUST BE REJECTED with 409 Conflict
        $res2 = $this->actingAs($this->courierUser2)
            ->postJson("/api/v1/courier/tasks/{$order->id}/claim");

        $res2->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    /**
     * Test 7: Courier can progress pickup task (PICKUP_ASSIGNED -> PICKED_UP -> AT_STORE).
     */
    public function test_courier_can_progress_pickup_task_to_picked_up_and_at_store(): void
    {
        CourierStoreAssignment::create([
            'courier_id' => $this->courierProfile1->id,
            'store_id' => $this->store->id,
            'recruited_by' => $this->storeOwner->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $order = $this->createStandardOrder('PICKUP_ASSIGNED', $this->courierUser1->id);

        // Step 1: PICKUP_ASSIGNED -> PICKED_UP
        $res1 = $this->actingAs($this->courierUser1)
            ->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
                'to_status' => 'PICKED_UP',
                'note' => 'Cucian sudah diambil dari customer.',
            ]);

        $res1->assertStatus(200)
            ->assertJsonPath('data.status', 'PICKED_UP');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'PICKED_UP',
        ]);

        // Step 2: PICKED_UP -> AT_STORE
        $res2 = $this->actingAs($this->courierUser1)
            ->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
                'to_status' => 'AT_STORE',
                'note' => 'Cucian diserahkan ke toko.',
            ]);

        $res2->assertStatus(200)
            ->assertJsonPath('data.status', 'AT_STORE');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'AT_STORE',
        ]);
    }

    /**
     * Test 8: Courier can claim delivery task and progress to completed.
     */
    public function test_courier_can_atomically_claim_delivery_task_and_complete(): void
    {
        CourierStoreAssignment::create([
            'courier_id' => $this->courierProfile1->id,
            'store_id' => $this->store->id,
            'recruited_by' => $this->storeOwner->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $order = $this->createStandardOrder('READY_FOR_DELIVERY');

        // Claim delivery task (READY_FOR_DELIVERY -> DELIVERY_ASSIGNED)
        $claimRes = $this->actingAs($this->courierUser1)
            ->postJson("/api/v1/courier/tasks/{$order->id}/claim");

        $claimRes->assertStatus(200)
            ->assertJsonPath('data.status', 'DELIVERY_ASSIGNED');

        // DELIVERY_ASSIGNED -> OUT_FOR_DELIVERY
        $outRes = $this->actingAs($this->courierUser1)
            ->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
                'to_status' => 'OUT_FOR_DELIVERY',
                'note' => 'Menuju alamat pengantaran.',
            ]);

        $outRes->assertStatus(200)
            ->assertJsonPath('data.status', 'OUT_FOR_DELIVERY');

        // OUT_FOR_DELIVERY -> COMPLETED
        $compRes = $this->actingAs($this->courierUser1)
            ->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
                'to_status' => 'COMPLETED',
                'note' => 'Cucian diterima pelanggan.',
            ]);

        $compRes->assertStatus(200)
            ->assertJsonPath('data.status', 'COMPLETED');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'COMPLETED',
        ]);
    }

    /**
     * Test 9: Unassigned courier cannot update another courier's task.
     */
    public function test_unassigned_courier_cannot_transition_another_couriers_task(): void
    {
        // Courier 1 is assigned, Courier 2 is also in store
        CourierStoreAssignment::create([
            'courier_id' => $this->courierProfile1->id,
            'store_id' => $this->store->id,
            'recruited_by' => $this->storeOwner->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        CourierStoreAssignment::create([
            'courier_id' => $this->courierProfile2->id,
            'store_id' => $this->store->id,
            'recruited_by' => $this->storeOwner->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $order = $this->createStandardOrder('PICKUP_ASSIGNED', $this->courierUser1->id);

        // Courier 2 tries to update status of Courier 1's order -> MUST FAIL with 403
        $response = $this->actingAs($this->courierUser2)
            ->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
                'to_status' => 'PICKED_UP',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'UNAUTHORIZED_COURIER');
    }

    /**
     * Test 10: Courier without active store assignment is rejected from task operations.
     */
    public function test_courier_without_active_assignment_is_rejected(): void
    {
        $order = $this->createStandardOrder('PENDING_PICKUP');

        // Courier 1 has NO active assignment
        $response = $this->actingAs($this->courierUser1)
            ->getJson('/api/v1/courier/tasks');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'NO_ACTIVE_STORE');
    }
}
