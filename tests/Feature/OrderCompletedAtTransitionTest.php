<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Store;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Services\Order\OrderService;
use App\Services\Settlement\SettlementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCompletedAtTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected OrderService $orderService;
    protected User $admin;
    protected User $storeOwner;
    protected User $customer;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->orderService = new OrderService();
        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->customer = User::factory()->create(['role' => 'customer']);
        $this->storeOwner = User::factory()->create(['role' => 'store_admin']);

        $this->store = Store::factory()->create(['owner_user_id' => $this->storeOwner->id]);

        SystemConfiguration::updateOrCreate(['key' => 'SETTLEMENT_PERIOD'], [
            'value' => ['interval' => 'weekly'],
            'description' => 'Settlement period',
            'is_editable' => true,
        ]);
        SystemConfiguration::updateOrCreate(['key' => 'MARKETPLACE_COMMISSION'], [
            'value' => ['rate_percent' => 10],
            'description' => 'Commission',
            'is_editable' => true,
        ]);
        SystemConfiguration::updateOrCreate(['key' => 'PLATFORM_FEE'], [
            'value' => ['fee_type' => 'fixed', 'amount' => 2000],
            'description' => 'Platform fee',
            'is_editable' => true,
        ]);
        SystemConfiguration::updateOrCreate(['key' => 'DISPUTE_SLA'], [
            'value' => ['resolution_days' => 7, 'first_response_hours' => 24],
            'description' => 'Dispute SLA',
            'is_editable' => true,
        ]);
    }

    /**
     * a. Transisi ke completed via OrderService (toko & admin) mengisi completed_at.
     */
    public function test_transisi_ke_completed_via_order_service_mengisi_completed_at(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'ORD-CMPL-001',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => OrderStatus::DELIVERED->value,
            'payment_status' => 'paid',
            'completed_at' => null,
        ]);

        $this->orderService->transition($order, OrderStatus::COMPLETED->value, $this->storeOwner);

        $fresh = $order->fresh();
        $this->assertEquals(OrderStatus::COMPLETED->value, $fresh->status);
        $this->assertNotNull($fresh->completed_at, 'OrderService::transition wajib mengisi completed_at saat status menjadi completed.');
    }

    /**
     * b. completed_at yang sudah terisi (jalur kurir) tidak ditimpa saat transisi.
     */
    public function test_completed_at_yang_sudah_terisi_tidak_ditimpa(): void
    {
        $existingTime = Carbon::now()->subHours(3);

        $order = Order::factory()->create([
            'order_number' => 'ORD-CMPL-002',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => OrderStatus::DELIVERED->value,
            'payment_status' => 'paid',
            'completed_at' => $existingTime,
        ]);

        $this->orderService->transition($order, OrderStatus::COMPLETED->value, $this->admin);

        $fresh = $order->fresh();
        $this->assertEquals($existingTime->toDateTimeString(), $fresh->completed_at->toDateTimeString(), 'completed_at yang sudah ada tidak boleh ditimpa.');
    }

    /**
     * c. Order selesai via toko eligible di SettlementService setelah dispute window.
     */
    public function test_order_selesai_via_toko_eligible_di_settlement_service_setelah_dispute_window(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'ORD-CMPL-003',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => OrderStatus::DELIVERED->value,
            'payment_status' => 'paid',
            'completed_at' => null,
            'total_amount' => 50000,
        ]);

        // Selesaikan pesanan lewat toko
        $this->orderService->transition($order, OrderStatus::COMPLETED->value, $this->storeOwner);
        $fresh = $order->fresh();
        $this->assertNotNull($fresh->completed_at);

        // Simulasikan waktu melewati dispute window (7 hari)
        $pastCompletedAt = Carbon::now()->subDays(10);
        $fresh->completed_at = $pastCompletedAt;
        $fresh->save();

        $settlementService = new SettlementService();
        $from = Carbon::now()->subDays(20);
        $to = Carbon::now();

        $eligible = $settlementService->eligibleOrders($this->store, $from, $to);

        $eligibleIds = array_map(fn($o) => $o->id, $eligible);
        $this->assertContains($fresh->id, $eligibleIds, 'Order selesai via toko harus eligible di SettlementService.');
    }
}
