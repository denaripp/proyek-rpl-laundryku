<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\PlatformLedgerEntry;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\ServiceCategory;
use App\Models\Store;
use App\Models\StoreLedgerEntry;
use App\Models\StoreService;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase8Test extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $storeOwner;
    protected Store $store;
    protected StoreService $serviceKiloan;
    protected User $courier;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'level' => 100]);
        Role::firstOrCreate(['code' => 'courier'], ['name' => 'Kurir', 'level' => 30]);
        Role::firstOrCreate(['code' => 'store_admin'], ['name' => 'Admin Toko', 'level' => 50]);
        Role::firstOrCreate(['code' => 'customer'], ['name' => 'Customer', 'level' => 10]);

        // 1. Super Admin
        $this->superAdmin = User::factory()->create([
            'phone' => '081299999999',
            'status' => 'active',
        ]);
        UserRole::create([
            'user_id' => $this->superAdmin->id,
            'role_id' => Role::where('code', 'super_admin')->first()->id,
            'status' => 'active',
        ]);

        // 2. Store Owner & Store
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

        $this->serviceKiloan = StoreService::create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'name' => 'Cuci Kering Lipat',
            'pricing_unit' => 'kg',
            'price_per_unit' => 10000,
            'minimum_charge' => 0,
            'estimated_duration_hours' => 24,
            'is_active' => true,
        ]);

        // 3. Courier
        $this->courier = User::factory()->create([
            'name' => 'Kurir Budi',
            'phone' => '081277777777',
            'status' => 'active',
        ]);
        UserRole::create([
            'user_id' => $this->courier->id,
            'role_id' => Role::where('code', 'courier')->first()->id,
            'status' => 'active',
        ]);
        $courierProfile = \App\Models\CourierProfile::create([
            'user_id' => $this->courier->id,
            'vehicle_type' => 'motorcycle',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        \App\Models\CourierStoreAssignment::create([
            'courier_id' => $courierProfile->id,
            'store_id' => $this->store->id,
            'recruited_by' => $this->storeOwner->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        // 4. Customer
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

        // 5. Seed default platform commission
        PlatformSetting::updateOrCreate(
            ['key' => 'platform_commission_flat'],
            [
                'value_json' => ['amount' => 1000],
                'description' => 'Besaran komisi platform flat per order selesai (Rupiah)',
            ]
        );
    }

    /**
     * Helper to create an order at AWAITING_PAYMENT status.
     */
    protected function createOrderAwaitingPayment(int $finalAmount = 40000): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'courier_id' => $this->courier->id,
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
            'status' => 'AWAITING_PAYMENT',
            'payment_status' => 'UNPAID',
            'estimated_weight_kg' => 4.0,
            'actual_weight_kg' => 4.0,
            'estimated_amount' => $finalAmount,
            'final_amount' => $finalAmount,
            'delivery_fee' => 0,
            'commission_amount' => 1000,
            'courier_wage' => 5000,
            'price_approved_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'service_id' => $this->serviceKiloan->id,
            'service_name_snapshot' => $this->serviceKiloan->name,
            'pricing_unit_snapshot' => 'kg',
            'unit_price_snapshot' => 10000,
            'minimum_charge_snapshot' => 0,
            'quantity_estimated' => 4.0,
            'quantity_actual' => 4.0,
            'line_estimated_amount' => $finalAmount,
            'line_final_amount' => $finalAmount,
        ]);

        return $order;
    }

    /**
     * Test 1: Customer initiates Virtual Account (VA) payment via Xendit.
     */
    public function test_customer_can_create_virtual_account_payment(): void
    {
        $order = $this->createOrderAwaitingPayment(35000);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/v1/orders/{$order->id}/pay", [
                'method' => 'va',
                'bank_code' => 'BCA',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.method', 'va')
            ->assertJsonPath('data.bank_code', 'BCA')
            ->assertJsonPath('data.amount', 35000)
            ->assertJsonPath('data.status', 'pending');

        $this->assertNotEmpty($response->json('data.va_number'));

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'va',
            'amount' => 35000,
            'status' => 'pending',
        ]);
    }

    /**
     * Test 2: Customer initiates QRIS payment via Xendit.
     */
    public function test_customer_can_create_qris_payment(): void
    {
        $order = $this->createOrderAwaitingPayment(45000);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/v1/orders/{$order->id}/pay", [
                'method' => 'qris',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.method', 'qris')
            ->assertJsonPath('data.amount', 45000)
            ->assertJsonPath('data.status', 'pending');

        $this->assertNotEmpty($response->json('data.qr_string'));
    }

    /**
     * Test 3: Customer chooses Cash on Delivery (COD).
     */
    public function test_customer_can_choose_cod_payment(): void
    {
        $order = $this->createOrderAwaitingPayment(50000);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/v1/orders/{$order->id}/pay", [
                'method' => 'cod',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.method', 'cod')
            ->assertJsonPath('data.amount', 50000)
            ->assertJsonPath('data.order_status', 'PAID');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'cod',
            'status' => 'pending',
        ]);

        $order->refresh();
        $this->assertEquals('PAID', $order->status);
    }

    /**
     * Test 4: Webhook Xendit verification and payment processing.
     */
    public function test_xendit_webhook_processes_payment_and_prevents_duplicate(): void
    {
        $order = $this->createOrderAwaitingPayment(40000);
        $token = config('services.xendit.webhook_token', 'test_xendit_webhook_token_12345');

        $payload = [
            'id' => 'evt_test_' . uniqid(),
            'external_id' => 'VA-' . $order->order_number . '-1234',
            'amount' => 40000,
            'status' => 'PAID',
            'payment_method' => 'VIRTUAL_ACCOUNT',
        ];

        // 1. Send Webhook with Valid Token
        $response = $this->withHeaders([
            'x-callback-token' => $token,
        ])->postJson('/api/v1/webhooks/xendit', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // Verify order advanced to PAID
        $order->refresh();
        $this->assertEquals('PAID', $order->status);
        $this->assertEquals('PAID', $order->payment_status);

        // Verify In-App Notification created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->id,
            'event_type' => 'PAYMENT_SUCCESS',
        ]);

        // 2. Send Duplicate Webhook -> Must be ignored gracefully
        $duplicateResponse = $this->withHeaders([
            'x-callback-token' => $token,
        ])->postJson('/api/v1/webhooks/xendit', $payload);

        $duplicateResponse->assertStatus(200)
            ->assertJsonPath('status', 'duplicate_ignored');
    }

    /**
     * Test 5: Courier confirms cash received on COD delivery.
     */
    public function test_courier_confirms_cod_payment_collection(): void
    {
        $order = $this->createOrderAwaitingPayment(60000);
        $order->status = 'OUT_FOR_DELIVERY';
        $order->save();

        $response = $this->actingAs($this->courier)
            ->postJson("/api/v1/courier/tasks/{$order->id}/confirm-cod");

        $response->assertStatus(200)
            ->assertJsonPath('data.payment_status', 'PAID')
            ->assertJsonPath('data.collected_amount', 60000);

        $order->refresh();
        $this->assertEquals('PAID', $order->payment_status);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'cod',
            'status' => 'paid',
        ]);
    }

    /**
     * Test 6: Order Completion triggers Store Ledger and Platform Ledger entries.
     */
    public function test_order_completion_records_ledger_entries(): void
    {
        $order = $this->createOrderAwaitingPayment(50000);
        $order->status = 'OUT_FOR_DELIVERY';
        $order->courier_wage = 5000;
        $order->save();

        Payment::create([
            'order_id' => $order->id,
            'provider' => 'xendit',
            'method' => 'va',
            'amount' => 50000,
            'status' => 'paid',
            'idempotency_key' => 'VA-ORD-TEST-' . $order->id,
            'paid_at' => now(),
        ]);

        // Complete Order (OUT_FOR_DELIVERY -> COMPLETED)
        $response = $this->actingAs($this->courier)
            ->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
                'to_status' => 'COMPLETED',
                'note' => 'Cucian telah diserahkan kepada customer.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'COMPLETED');

        // 1. Verify Store Ledger: PAYMENT_RECEIPT (Rp50.000 credit)
        $this->assertDatabaseHas('store_ledger_entries', [
            'store_id' => $this->store->id,
            'order_id' => $order->id,
            'entry_type' => 'PAYMENT_RECEIPT',
            'direction' => 'credit',
            'amount' => 50000,
        ]);

        // 2. Verify Store Ledger: COURIER_WAGE (Rp5.000 debit)
        $this->assertDatabaseHas('store_ledger_entries', [
            'store_id' => $this->store->id,
            'order_id' => $order->id,
            'entry_type' => 'COURIER_WAGE',
            'direction' => 'debit',
            'amount' => 5000,
        ]);

        // 3. Verify Store Ledger: PLATFORM_COMMISSION (Rp1.000 debit)
        $this->assertDatabaseHas('store_ledger_entries', [
            'store_id' => $this->store->id,
            'order_id' => $order->id,
            'entry_type' => 'PLATFORM_COMMISSION',
            'direction' => 'debit',
            'amount' => 1000,
        ]);

        // 4. Verify Platform Ledger: PLATFORM_COMMISSION (Rp1.000 accrued)
        $this->assertDatabaseHas('platform_ledger_entries', [
            'store_id' => $this->store->id,
            'order_id' => $order->id,
            'entry_type' => 'PLATFORM_COMMISSION',
            'amount' => 1000,
            'status' => 'accrued',
        ]);
    }

    /**
     * Test 7: Super Admin gets and updates Platform Commission configuration.
     */
    public function test_platform_commission_configuration_can_be_updated_by_super_admin(): void
    {
        // 1. Get Commission Config
        $getResponse = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/platform-settings/commission');

        $getResponse->assertStatus(200)
            ->assertJsonPath('data.amount', 1000);

        // 2. Update Commission Config to Rp1.500
        $patchResponse = $this->actingAs($this->superAdmin)
            ->patchJson('/api/v1/admin/platform-settings/commission', [
                'amount' => 1500,
                'description' => 'Penyesuaian komisi platform 2026',
            ]);

        $patchResponse->assertStatus(200)
            ->assertJsonPath('data.amount', 1500);

        $this->assertEquals(1500, PlatformSetting::getPlatformCommission());
    }

    /**
     * Test 8: Store Admin views Store Ledger summary and entries.
     */
    public function test_store_admin_can_view_store_ledger(): void
    {
        // Create sample entries
        StoreLedgerEntry::create([
            'store_id' => $this->store->id,
            'entry_type' => 'PAYMENT_RECEIPT',
            'direction' => 'credit',
            'amount' => 100000,
            'description' => 'Pemasukan order #1',
        ]);

        StoreLedgerEntry::create([
            'store_id' => $this->store->id,
            'entry_type' => 'COURIER_WAGE',
            'direction' => 'debit',
            'amount' => 10000,
            'description' => 'Upah kurir #1',
        ]);

        $response = $this->actingAs($this->storeOwner)
            ->getJson('/api/v1/store/ledger');

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_income', 100000)
            ->assertJsonPath('data.summary.total_expense', 10000)
            ->assertJsonPath('data.summary.net_balance', 90000)
            ->assertJsonCount(2, 'data.entries');
    }
}
