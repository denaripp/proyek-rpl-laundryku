<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Role;
use App\Models\ServiceCategory;
use App\Models\Store;
use App\Models\StoreService;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireUnpaidOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected Store $store;
    protected StoreService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'level' => 100]);
        Role::firstOrCreate(['code' => 'courier'], ['name' => 'Kurir', 'level' => 30]);
        Role::firstOrCreate(['code' => 'store_admin'], ['name' => 'Admin Toko', 'level' => 50]);
        Role::firstOrCreate(['code' => 'customer'], ['name' => 'Customer', 'level' => 10]);

        $owner = User::factory()->create(['status' => 'active']);
        UserRole::create([
            'user_id' => $owner->id,
            'role_id' => Role::where('code', 'store_admin')->first()->id,
            'status' => 'active',
        ]);

        $this->store = Store::create([
            'owner_user_id' => $owner->id,
            'name' => 'Super Clean Laundry',
            'address_text' => 'Jl. Merdeka No. 1, Bandung',
            'latitude' => -6.9000,
            'longitude' => 107.6000,
            'service_radius_km' => 10.0,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        $category = ServiceCategory::create([
            'name' => 'Kiloan',
            'slug' => 'kiloan',
            'pricing_type' => 'per_kg',
        ]);

        $this->service = StoreService::create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'name' => 'Cuci Lipat Reguler',
            'pricing_unit' => 'kg',
            'price_per_unit' => 7000,
            'minimum_charge' => 7000,
            'estimated_duration_hours' => 24,
            'is_active' => true,
        ]);

        $this->customer = User::factory()->create(['status' => 'active']);
        UserRole::create([
            'user_id' => $this->customer->id,
            'role_id' => Role::where('code', 'customer')->first()->id,
            'status' => 'active',
        ]);
    }

    /**
     * Test 1: Order lewat batas waktu pembayaran otomatis kedaluwarsa (PAYMENT_EXPIRED).
     */
    public function test_order_past_expiry_deadline_becomes_payment_expired(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-20261004-EXP01',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'pickup_address_snapshot' => ['address_text' => 'Jl. Test No. 1'],
            'delivery_address_snapshot' => ['address_text' => 'Jl. Test No. 1'],
            'status' => 'AWAITING_PAYMENT',
            'payment_status' => 'UNPAID',
            'estimated_amount' => 20000,
            'final_amount' => 20000,
            'price_approved_at' => now()->subHours(25), // > 24 hours ago
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'xendit',
            'method' => 'va',
            'amount' => 20000,
            'status' => 'pending',
            'idempotency_key' => 'VA-ORD-EXP01',
            'payment_details' => [
                'expiration_date' => now()->subHours(2)->toIso8601String(), // Explicit past expiry
            ],
        ]);

        $this->artisan('orders:expire-unpaid')
            ->assertSuccessful();

        $freshOrder = $order->fresh();
        $this->assertEquals('PAYMENT_EXPIRED', $freshOrder->status);
        $this->assertEquals('PAYMENT_EXPIRED', $freshOrder->payment_status);
        $this->assertNotNull($freshOrder->cancelled_at);

        $freshPayment = $payment->fresh();
        $this->assertEquals('expired', $freshPayment->status);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => 'AWAITING_PAYMENT',
            'to_status' => 'PAYMENT_EXPIRED',
        ]);
    }

    /**
     * Test 2: Order masih dalam batas waktu pembayaran tidak berubah.
     */
    public function test_order_within_expiry_deadline_remains_awaiting_payment(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-20261004-VALID01',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'pickup_address_snapshot' => ['address_text' => 'Jl. Test No. 1'],
            'delivery_address_snapshot' => ['address_text' => 'Jl. Test No. 1'],
            'status' => 'AWAITING_PAYMENT',
            'payment_status' => 'UNPAID',
            'estimated_amount' => 20000,
            'final_amount' => 20000,
            'price_approved_at' => now()->subMinutes(10), // only 10 minutes ago
        ]);

        Payment::create([
            'order_id' => $order->id,
            'provider' => 'xendit',
            'method' => 'qris',
            'amount' => 20000,
            'status' => 'pending',
            'idempotency_key' => 'QRIS-ORD-VALID01',
            'payment_details' => [
                'expires_at' => now()->addMinutes(20)->toIso8601String(), // 20 mins remaining
            ],
        ]);

        $this->artisan('orders:expire-unpaid')
            ->assertSuccessful();

        $freshOrder = $order->fresh();
        $this->assertEquals('AWAITING_PAYMENT', $freshOrder->status);
        $this->assertEquals('UNPAID', $freshOrder->payment_status);
    }

    /**
     * Test 3: Order yang sudah PAID tidak diubah walaupun batas waktu lama.
     */
    public function test_paid_order_is_not_expired_by_scheduler(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-20261004-PAID01',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'pickup_address_snapshot' => ['address_text' => 'Jl. Test No. 1'],
            'delivery_address_snapshot' => ['address_text' => 'Jl. Test No. 1'],
            'status' => 'PAID',
            'payment_status' => 'PAID',
            'estimated_amount' => 20000,
            'final_amount' => 20000,
            'price_approved_at' => now()->subHours(48),
        ]);

        $this->artisan('orders:expire-unpaid')
            ->assertSuccessful();

        $freshOrder = $order->fresh();
        $this->assertEquals('PAID', $freshOrder->status);
        $this->assertEquals('PAID', $freshOrder->payment_status);
    }

    /**
     * Test 4: Webhook 'paid' yang tiba saat order diproses tidak menghasilkan PAYMENT_EXPIRED.
     */
    public function test_concurrent_paid_webhook_and_expire_command_preserves_paid_status(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-20261004-RACE01',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'pickup_address_snapshot' => ['address_text' => 'Jl. Test No. 1'],
            'delivery_address_snapshot' => ['address_text' => 'Jl. Test No. 1'],
            'status' => 'AWAITING_PAYMENT',
            'payment_status' => 'UNPAID',
            'estimated_amount' => 25000,
            'final_amount' => 25000,
            'price_approved_at' => now()->subHours(25),
        ]);

        Payment::create([
            'order_id' => $order->id,
            'provider' => 'xendit',
            'method' => 'va',
            'amount' => 25000,
            'status' => 'pending',
            'idempotency_key' => 'VA-ORD-RACE01',
            'payment_details' => [
                'expiration_date' => now()->subMinutes(5)->toIso8601String(),
            ],
        ]);

        $token = config('services.xendit.webhook_token', 'test_token');

        // Webhook Xendit memverifikasi pembayaran lunas
        $response = $this->withHeaders([
            'x-callback-token' => $token,
        ])->postJson('/api/v1/webhooks/xendit', [
            'id' => 'evt_race_paid_001',
            'external_id' => 'VA-' . $order->order_number,
            'status' => 'PAID',
            'amount' => 25000,
            'paid_at' => now()->toIso8601String(),
        ]);

        $response->assertSuccessful();

        // Jalankan perintah expire
        $this->artisan('orders:expire-unpaid')
            ->assertSuccessful();

        // Verifikasi status order tetap PAID dan tidak ditimpa menjadi PAYMENT_EXPIRED
        $freshOrder = $order->fresh();
        $this->assertEquals('PAID', $freshOrder->status);
        $this->assertEquals('PAID', $freshOrder->payment_status);
        $this->assertNull($freshOrder->cancelled_at);
    }
}
