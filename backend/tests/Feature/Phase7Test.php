<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\ServiceCategory;
use App\Models\Store;
use App\Models\StoreService;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7Test extends TestCase
{
    use RefreshDatabase;

    protected User $storeOwner;
    protected Store $store;
    protected StoreService $serviceKiloan;
    protected StoreService $serviceBedcover;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'level' => 100]);
        Role::firstOrCreate(['code' => 'courier'], ['name' => 'Kurir', 'level' => 30]);
        Role::firstOrCreate(['code' => 'store_admin'], ['name' => 'Admin Toko', 'level' => 50]);
        Role::firstOrCreate(['code' => 'customer'], ['name' => 'Customer', 'level' => 10]);

        // Create Store Owner & Store
        $this->storeOwner = User::factory()->create([
            'phone' => '081255555555',
            'status' => 'active',
        ]);
        UserRole::create([
            'user_id' => $this->storeOwner->id,
            'role_id' => Role::where('code', 'store_admin')->first()->id,
            'status' => 'active',
        ]);

        $this->store = Store::create([
            'owner_user_id' => $this->storeOwner->id,
            'name' => 'Laundry Express Clean',
            'address_text' => 'Jl. Dipatiukur No. 50, Bandung',
            'latitude' => -6.8900,
            'longitude' => 107.6100,
            'service_radius_km' => 5.0,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        $category = ServiceCategory::create([
            'name' => 'Kiloan Reguler',
            'slug' => 'kiloan-reguler',
            'status' => 'active',
        ]);

        // Service 1: Kiloan @ Rp10.000 / kg, min charge Rp20.000 (equivalent to 2kg min)
        $this->serviceKiloan = StoreService::create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'name' => 'Cuci Kering Lipat',
            'pricing_unit' => 'kg',
            'price_per_unit' => 10000,
            'minimum_charge' => 20000,
            'estimated_duration_hours' => 24,
            'is_active' => true,
        ]);

        // Service 2: Satuan @ Rp25.000 / item
        $this->serviceBedcover = StoreService::create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'name' => 'Cuci Bedcover Besar',
            'pricing_unit' => 'item',
            'price_per_unit' => 25000,
            'minimum_charge' => 0,
            'estimated_duration_hours' => 48,
            'is_active' => true,
        ]);

        // Create Customer
        $this->customer = User::factory()->create([
            'name' => 'Dewi Pelanggan',
            'phone' => '081266666666',
            'status' => 'active',
        ]);
        UserRole::create([
            'user_id' => $this->customer->id,
            'role_id' => Role::where('code', 'customer')->first()->id,
            'status' => 'active',
        ]);
    }

    /**
     * Helper to create an order at AT_STORE status ready for weighing.
     */
    protected function createOrderAtStore(float $estimatedKg = 3.0): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'pickup_address_snapshot' => [
                'label' => 'Rumah',
                'address_text' => 'Jl. Tubagus Ismail No. 10',
                'latitude' => -6.8910,
                'longitude' => 107.6120,
            ],
            'delivery_address_snapshot' => [
                'label' => 'Rumah',
                'address_text' => 'Jl. Tubagus Ismail No. 10',
                'latitude' => -6.8910,
                'longitude' => 107.6120,
            ],
            'status' => 'AT_STORE',
            'payment_status' => 'UNPAID',
            'estimated_weight_kg' => $estimatedKg,
            'actual_weight_kg' => null,
            'estimated_amount' => (int) round($estimatedKg * $this->serviceKiloan->price_per_unit),
            'final_amount' => null,
            'delivery_fee' => 0,
            'commission_amount' => 1000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'service_id' => $this->serviceKiloan->id,
            'service_name_snapshot' => $this->serviceKiloan->name,
            'pricing_unit_snapshot' => $this->serviceKiloan->pricing_unit,
            'unit_price_snapshot' => $this->serviceKiloan->price_per_unit,
            'minimum_charge_snapshot' => $this->serviceKiloan->minimum_charge,
            'quantity_estimated' => $estimatedKg,
            'quantity_actual' => null,
            'line_estimated_amount' => (int) round($estimatedKg * $this->serviceKiloan->price_per_unit),
            'line_final_amount' => null,
        ]);

        return $order;
    }

    /**
     * Test 1: Store Admin inputs actual weight -> Status moves to WEIGHED then AWAITING_PRICE_APPROVAL, and Notification created.
     */
    public function test_store_admin_weighing_calculates_final_price_and_dispatches_notification(): void
    {
        $order = $this->createOrderAtStore(3.0);

        // Store Admin submits weighing (actual_weight_kg = 3.5 kg)
        $response = $this->actingAs($this->storeOwner)
            ->postJson("/api/v1/orders/{$order->id}/transition", [
                'to_status' => 'WEIGHED',
                'actual_weight_kg' => 3.5,
                'note' => 'Penimbangan selesai: 3.5 kg.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'WEIGHED')
            ->assertJsonPath('data.actual_weight_kg', 3.5)
            ->assertJsonPath('data.final_amount', 35000); // 3.5 * 10.000 = 35.000

        // Auto transition to AWAITING_PRICE_APPROVAL
        $awaitResponse = $this->actingAs($this->storeOwner)
            ->postJson("/api/v1/orders/{$order->id}/transition", [
                'to_status' => 'AWAITING_PRICE_APPROVAL',
                'note' => 'Menunggu persetujuan customer.',
            ]);

        $awaitResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'AWAITING_PRICE_APPROVAL');

        // Verify In-App Notification created for customer
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->id,
            'event_type' => 'ORDER_STATUS_CHANGED',
        ]);
    }

    /**
     * Test 2: Scenario Final Price EQUAL to Estimated Price -> Customer Approves.
     */
    public function test_scenario_final_price_equal_to_estimate_customer_approves(): void
    {
        $order = $this->createOrderAtStore(3.0); // Estimated 3.0 kg = 30.000

        // Weighed at exactly 3.0 kg
        $this->actingAs($this->storeOwner)
            ->postJson("/api/v1/orders/{$order->id}/transition", [
                'to_status' => 'WEIGHED',
                'actual_weight_kg' => 3.0,
            ]);

        $this->actingAs($this->storeOwner)
            ->postJson("/api/v1/orders/{$order->id}/transition", [
                'to_status' => 'AWAITING_PRICE_APPROVAL',
            ]);

        $freshOrder = $order->fresh();
        $this->assertEquals(30000, $freshOrder->estimated_amount);
        $this->assertEquals(30000, $freshOrder->final_amount);
        $this->assertEquals($freshOrder->estimated_amount, $freshOrder->final_amount); // Same price

        // Customer Approves via dedicated price-approval endpoint
        $response = $this->actingAs($this->customer)
            ->postJson("/api/v1/orders/{$order->id}/price-approval", [
                'action' => 'approve',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'AWAITING_PAYMENT'); // Auto advanced to AWAITING_PAYMENT

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'AWAITING_PAYMENT',
        ]);
    }

    /**
     * Test 3: Scenario Final Price HIGHER than Estimate -> Customer Approves.
     */
    public function test_scenario_final_price_higher_than_estimate_customer_approves(): void
    {
        $order = $this->createOrderAtStore(2.0); // Estimated 2.0 kg = 20.000

        // Actual weight is heavier: 4.5 kg -> Final price = 45.000
        $this->actingAs($this->storeOwner)
            ->postJson("/api/v1/orders/{$order->id}/transition", [
                'to_status' => 'WEIGHED',
                'actual_weight_kg' => 4.5,
            ]);

        $this->actingAs($this->storeOwner)
            ->postJson("/api/v1/orders/{$order->id}/transition", [
                'to_status' => 'AWAITING_PRICE_APPROVAL',
            ]);

        $freshOrder = $order->fresh();
        $this->assertEquals(20000, $freshOrder->estimated_amount);
        $this->assertEquals(45000, $freshOrder->final_amount);
        $this->assertGreaterThan($freshOrder->estimated_amount, $freshOrder->final_amount);

        // Customer reviews price difference and decides to approve
        $response = $this->actingAs($this->customer)
            ->postJson("/api/v1/orders/{$order->id}/price-approval", [
                'action' => 'approve',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'AWAITING_PAYMENT');
    }

    /**
     * Test 4: Scenario Final Price DIFFERENT -> Customer Rejects and Order is CANCELLED (US-CUS-07).
     */
    public function test_scenario_final_price_different_customer_rejects_and_cancels_order(): void
    {
        $order = $this->createOrderAtStore(2.0); // Estimated 2.0 kg = 20.000

        // Actual weight is 6.0 kg -> Final price = 60.000 (triple the estimate)
        $this->actingAs($this->storeOwner)
            ->postJson("/api/v1/orders/{$order->id}/transition", [
                'to_status' => 'WEIGHED',
                'actual_weight_kg' => 6.0,
            ]);

        $this->actingAs($this->storeOwner)
            ->postJson("/api/v1/orders/{$order->id}/transition", [
                'to_status' => 'AWAITING_PRICE_APPROVAL',
            ]);

        // Customer rejects final price due to difference
        $response = $this->actingAs($this->customer)
            ->postJson("/api/v1/orders/{$order->id}/price-approval", [
                'action' => 'reject',
                'reason' => 'Harga final Rp60.000 melebihi anggaran saya.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'CANCELLED');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'CANCELLED',
        ]);
    }

    /**
     * Test 5: Order CANNOT progress to PROCESSING before customer approves price (FR-PRICE-06).
     */
    public function test_order_cannot_progress_to_processing_without_customer_approval(): void
    {
        $order = $this->createOrderAtStore(3.0);

        $this->actingAs($this->storeOwner)
            ->postJson("/api/v1/orders/{$order->id}/transition", [
                'to_status' => 'WEIGHED',
                'actual_weight_kg' => 3.5,
            ]);

        $this->actingAs($this->storeOwner)
            ->postJson("/api/v1/orders/{$order->id}/transition", [
                'to_status' => 'AWAITING_PRICE_APPROVAL',
            ]);

        // Store Admin attempts to bypass and start washing (PROCESSING) -> MUST BE REJECTED with 409 Conflict
        $response = $this->actingAs($this->storeOwner)
            ->postJson("/api/v1/orders/{$order->id}/transition", [
                'to_status' => 'PROCESSING',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
    }

    /**
     * Test 6: Minimum Charge constraint applies in final price calculation (FR-PRICE-03).
     */
    public function test_minimum_charge_applied_when_actual_weight_is_below_minimum(): void
    {
        $order = $this->createOrderAtStore(1.0); // Estimated 1.0 kg

        // Actual weight is 1.0 kg (Raw = 1.0 * 10.000 = 10.000, but Minimum Charge is 20.000)
        $this->actingAs($this->storeOwner)
            ->postJson("/api/v1/orders/{$order->id}/transition", [
                'to_status' => 'WEIGHED',
                'actual_weight_kg' => 1.0,
            ]);

        $freshOrder = $order->fresh(['items']);
        $this->assertEquals(20000, $freshOrder->final_amount); // Minimum charge applied
        $this->assertEquals(20000, $freshOrder->items->first()->line_final_amount);
    }

    /**
     * Test 7: Customer can fetch and mark in-app notifications as read (PRD 11.8).
     */
    public function test_customer_can_fetch_and_mark_notifications_as_read(): void
    {
        $notification = Notification::create([
            'user_id' => $this->customer->id,
            'channel' => 'in_app',
            'event_type' => 'PRICE_APPROVAL_REQUIRED',
            'title' => 'Persetujuan Harga Final Diperlukan',
            'message' => 'Silakan tinjau harga final cucian Anda.',
            'status' => 'unread',
        ]);

        // 1. Fetch notifications
        $resList = $this->actingAs($this->customer)
            ->getJson('/api/v1/notifications');

        $resList->assertStatus(200)
            ->assertJsonPath('data.unread_count', 1);

        // 2. Mark as read
        $resRead = $this->actingAs($this->customer)
            ->patchJson("/api/v1/notifications/{$notification->id}/read");

        $resRead->assertStatus(200)
            ->assertJsonPath('data.status', 'read');

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'status' => 'read',
        ]);
    }
}
