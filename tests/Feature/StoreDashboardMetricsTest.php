<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected User $storeAdminA;
    protected Store $storeA;
    protected User $storeAdminB;
    protected Store $storeB;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'name' => 'Pelanggan Uji',
            'is_active' => true,
        ]);

        $this->storeAdminA = User::factory()->create([
            'role' => User::ROLE_STORE_ADMIN,
            'name' => 'Admin Toko A',
            'email' => 'adminA@test.com',
            'is_active' => true,
        ]);
        $storeAdminRole = Role::where('name', 'store_admin')->first();
        if ($storeAdminRole) {
            $this->storeAdminA->roles()->attach($storeAdminRole->id);
        }

        $this->storeA = Store::factory()->create([
            'owner_user_id' => $this->storeAdminA->id,
            'name' => 'Toko Laundry A',
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->storeAdminB = User::factory()->create([
            'role' => User::ROLE_STORE_ADMIN,
            'name' => 'Admin Toko B',
            'email' => 'adminB@test.com',
            'is_active' => true,
        ]);
        if ($storeAdminRole) {
            $this->storeAdminB->roles()->attach($storeAdminRole->id);
        }

        $this->storeB = Store::factory()->create([
            'owner_user_id' => $this->storeAdminB->id,
            'name' => 'Toko Laundry B',
            'status' => 'approved',
            'is_active' => true,
        ]);
    }

    /**
     * Skenario 1: Pembayaran sukses hari ini terhitung meski pesanan belum completed (misal status processing / paid).
     */
    public function test_today_revenue_counts_paid_payments_today_even_when_order_not_completed(): void
    {
        Sanctum::actingAs($this->storeAdminA, ['role:store_admin']);

        $order = Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'status' => 'processing',
            'total_amount' => 25000,
            'completed_at' => null,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'online',
            'amount' => 25000,
            'status' => 'paid',
            'paid_at' => Carbon::now('Asia/Jakarta'),
        ]);

        $res = $this->getJson('/api/v1/store/dashboard');

        $res->assertStatus(200);
        $this->assertEquals(25000.0, (float) $res->json('data.metrics.today_revenue'));
    }

    /**
     * Skenario 2: Pembayaran kemarin tidak terhitung.
     */
    public function test_today_revenue_excludes_yesterday_payments(): void
    {
        Sanctum::actingAs($this->storeAdminA, ['role:store_admin']);

        $order = Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'status' => 'completed',
            'total_amount' => 30000,
            'completed_at' => Carbon::now('Asia/Jakarta')->subDay(),
        ]);

        Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'online',
            'amount' => 30000,
            'status' => 'paid',
            'paid_at' => Carbon::now('Asia/Jakarta')->subDay(),
        ]);

        $res = $this->getJson('/api/v1/store/dashboard');

        $res->assertStatus(200);
        $this->assertEquals(0.0, (float) $res->json('data.metrics.today_revenue'));
    }

    /**
     * Skenario 3: Pembayaran toko lain tidak terhitung (isolasi multi-tenant).
     */
    public function test_today_revenue_excludes_other_store_payments(): void
    {
        Sanctum::actingAs($this->storeAdminA, ['role:store_admin']);

        $orderB = Order::factory()->create([
            'store_id' => $this->storeB->id,
            'customer_id' => $this->customer->id,
            'status' => 'processing',
            'total_amount' => 99000,
        ]);

        Payment::create([
            'order_id' => $orderB->id,
            'payment_method' => 'online',
            'amount' => 99000,
            'status' => 'paid',
            'paid_at' => Carbon::now('Asia/Jakarta'),
        ]);

        $res = $this->getJson('/api/v1/store/dashboard');

        $res->assertStatus(200);
        $this->assertEquals(0.0, (float) $res->json('data.metrics.today_revenue'));
    }

    /**
     * Skenario 4: Pembayaran pending dan gagal tidak terhitung.
     */
    public function test_today_revenue_excludes_pending_and_failed_payments(): void
    {
        Sanctum::actingAs($this->storeAdminA, ['role:store_admin']);

        $orderPending = Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'status' => 'awaiting_payment',
            'total_amount' => 45000,
        ]);
        Payment::create([
            'order_id' => $orderPending->id,
            'payment_method' => 'online',
            'amount' => 45000,
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $orderFailed = Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'status' => 'cancelled',
            'total_amount' => 15000,
        ]);
        Payment::create([
            'order_id' => $orderFailed->id,
            'payment_method' => 'online',
            'amount' => 15000,
            'status' => 'failed',
            'paid_at' => Carbon::now('Asia/Jakarta'),
        ]);

        $res = $this->getJson('/api/v1/store/dashboard');

        $res->assertStatus(200);
        $this->assertEquals(0.0, (float) $res->json('data.metrics.today_revenue'));
    }

    /**
     * Skenario 4b: Beberapa order dengan pembayaran sukses dijumlahkan akurat tanpa duplikasi.
     */
    public function test_today_revenue_sums_multiple_payments_accurately_without_duplication(): void
    {
        Sanctum::actingAs($this->storeAdminA, ['role:store_admin']);

        $order1 = Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'status' => 'processing',
            'total_amount' => 10000,
        ]);
        Payment::create([
            'order_id' => $order1->id,
            'payment_method' => 'online',
            'amount' => 10000,
            'status' => 'paid',
            'paid_at' => Carbon::now('Asia/Jakarta'),
        ]);

        $order2 = Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'status' => 'paid',
            'total_amount' => 13100,
        ]);
        Payment::create([
            'order_id' => $order2->id,
            'payment_method' => 'qris',
            'amount' => 13100,
            'status' => 'paid',
            'paid_at' => Carbon::now('Asia/Jakarta'),
        ]);

        $res = $this->getJson('/api/v1/store/dashboard');

        $res->assertStatus(200);
        $this->assertEquals(23100.0, (float) $res->json('data.metrics.today_revenue'));
    }

    /**
     * Skenario 5: Status courier_assigned_for_delivery terhitung di Siap Diantar.
     */
    public function test_ready_for_delivery_includes_courier_assigned_for_delivery(): void
    {
        Sanctum::actingAs($this->storeAdminA, ['role:store_admin']);

        Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'status' => 'courier_assigned_for_delivery',
        ]);
        Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'status' => 'ready_for_delivery',
        ]);

        $res = $this->getJson('/api/v1/store/dashboard');

        $res->assertStatus(200);
        $this->assertSame(2, $res->json('data.metrics.ready_for_delivery'));
    }

    /**
     * Skenario 6: Selesai hari ini tetap memakai completed_at hari ini.
     */
    public function test_completed_today_still_counts_orders_completed_today(): void
    {
        Sanctum::actingAs($this->storeAdminA, ['role:store_admin']);

        Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'status' => 'completed',
            'completed_at' => now()->subDays(2),
        ]);

        $res = $this->getJson('/api/v1/store/dashboard');

        $res->assertStatus(200);
        $this->assertSame(1, $res->json('data.metrics.completed_today'));
    }
}
