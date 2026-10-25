<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Store;
use App\Models\User;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiClientInterface;
use App\Services\Ai\AiOrderStatsService;
use App\Services\Ai\AiToolRegistry;
use App\Services\Ai\AiTopicGuardrail;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FakeStatsAiClient implements AiClientInterface
{
    public array $calls = [];

    public function chat(array $messages, array $tools): array
    {
        $this->calls[] = ['messages' => $messages, 'tools' => $tools];

        return [
            'content' => 'Mock reply',
            'tool_calls' => [],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
        ];
    }
}

class AiOrderStatsTest extends TestCase
{
    use RefreshDatabase;

    protected FakeStatsAiClient $client;
    protected AiTopicGuardrail $guardrail;
    protected AiOrderStatsService $statsService;
    protected AiAssistantService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new FakeStatsAiClient();
        $this->guardrail = new AiTopicGuardrail();
        $this->statsService = new AiOrderStatsService();
        $this->service = new AiAssistantService(
            $this->client,
            new AiToolRegistry(),
            $this->guardrail,
            $this->statsService
        );
    }

    /**
     * 1. Customer dengan pesanan: total pesanan, rincian status, pesanan terakhir, dan total pengeluaran.
     */
    public function test_customer_order_statistics_calculation(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $store = Store::factory()->create();

        // 2 pesanan selesai dan lunas (total 50.000 + 75.000 = 125.000)
        Order::factory()->create([
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'order_number' => 'ORD-1001',
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => 'paid',
            'total_amount' => 50000,
            'order_date' => Carbon::now('Asia/Jakarta')->subDays(3),
        ]);

        Order::factory()->create([
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'order_number' => 'ORD-1002',
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => 'paid',
            'total_amount' => 75000,
            'order_date' => Carbon::now('Asia/Jakarta')->subDays(2),
        ]);

        // 1 pesanan sedang diproses
        Order::factory()->create([
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'order_number' => 'ORD-1003',
            'status' => OrderStatus::PROCESSING->value,
            'payment_status' => 'paid',
            'total_amount' => 40000,
            'order_date' => Carbon::now('Asia/Jakarta')->subDay(),
        ]);

        // 1 pesanan dibatalkan
        Order::factory()->create([
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'order_number' => 'ORD-1004',
            'status' => OrderStatus::CANCELLED->value,
            'payment_status' => 'pending',
            'total_amount' => 30000,
            'order_date' => Carbon::now('Asia/Jakarta')->subHours(5),
        ]);

        // 1 pesanan selesai tapi di-refund (tidak boleh dihitung ke total pengeluaran!)
        $refundedOrder = Order::factory()->create([
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'order_number' => 'ORD-1005',
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => 'paid',
            'total_amount' => 60000,
            'order_date' => Carbon::now('Asia/Jakarta')->subHours(2),
        ]);

        $payment = \App\Models\Payment::create([
            'order_id' => $refundedOrder->id,
            'payment_method' => 'qris',
            'status' => 'paid',
            'amount' => 60000,
        ]);

        Refund::create([
            'order_id' => $refundedOrder->id,
            'payment_id' => $payment->id,
            'amount' => 60000,
            'status' => 'completed',
            'method' => 'manual',
            'reason' => 'Pakaian rusak',
            'requested_by' => $customer->id,
        ]);

        // Total orders: 5 (3 completed, 1 processing, 1 cancelled)
        $resCount = $this->service->chat($customer, null, 'berapa kali saya memesan');
        $this->assertCount(0, $this->client->calls, 'Statistik murni tidak boleh memanggil provider LLM!');
        $this->assertEquals('Anda sudah memesan 5 kali. 3 selesai, 1 diproses, 1 dibatalkan.', $resCount['reply']);

        // Query pesanan terakhir (ORD-1005)
        $resLast = $this->service->chat($customer, null, 'pesanan terakhir saya');
        $this->assertCount(0, $this->client->calls);
        $this->assertStringContainsString('ORD-1005', $resLast['reply']);
        $this->assertStringContainsString('WIB', $resLast['reply']);

        // Query total pengeluaran: harus 125.000 (mengecualikan 60.000 yang di-refund, dan mengecualikan cancelled/processing)
        $resSpend = $this->service->chat($customer, null, 'total pengeluaran saya berapa');
        $this->assertCount(0, $this->client->calls);
        $this->assertStringContainsString('Rp 125.000', $resSpend['reply']);

        // Query pesanan diproses
        $resProc = $this->service->chat($customer, null, 'berapa pesanan yang sedang diproses');
        $this->assertStringContainsString('1 pesanan yang sedang diproses', $resProc['reply']);

        // Query pesanan selesai
        $resDone = $this->service->chat($customer, null, 'pesanan selesai saya berapa');
        $this->assertStringContainsString('3 pesanan yang telah selesai', $resDone['reply']);
    }

    /**
     * 2. Customer tanpa pesanan: dijawab belum memiliki pesanan, bukan mengarang angka.
     */
    public function test_customer_with_zero_orders_reports_no_orders(): void
    {
        $newCustomer = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        $res = $this->service->chat($newCustomer, null, 'berapa kali saya memesan');
        $this->assertEquals('Anda belum memiliki pesanan di LaundryKu.', $res['reply']);

        $resLast = $this->service->chat($newCustomer, null, 'pesanan terakhir saya');
        $this->assertEquals('Anda belum memiliki pesanan di LaundryKu.', $resLast['reply']);

        $resSpend = $this->service->chat($newCustomer, null, 'total pengeluaran saya');
        $this->assertStringContainsString('Rp 0', $resSpend['reply']);
    }

    /**
     * 3. Pemilik / Admin Toko: statistik pesanan toko yang dikelola dan jumlah pesanan hari ini (WIB).
     */
    public function test_store_owner_statistics_and_today_orders(): void
    {
        $owner = User::factory()->create(['role' => 'store_admin', 'is_active' => true]);
        $store = Store::factory()->create(['owner_user_id' => $owner->id, 'name' => 'Laundry Berkah Utama']);

        $tz = config('app.timezone', 'UTC');
        // Pesan kemarin
        Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::COMPLETED->value,
            'created_at' => Carbon::now('Asia/Jakarta')->subDays(2)->setTimezone($tz),
            'order_date' => Carbon::now('Asia/Jakarta')->subDays(2)->setTimezone($tz),
        ]);

        // Pesan hari ini (WIB)
        Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::PROCESSING->value,
            'created_at' => Carbon::now('Asia/Jakarta')->startOfDay()->addHours(2)->setTimezone($tz),
            'order_date' => Carbon::now('Asia/Jakarta')->startOfDay()->addHours(2)->setTimezone($tz),
        ]);

        $res = $this->service->chat($owner, null, 'berapa pesanan masuk toko saya');
        $this->assertCount(0, $this->client->calls);
        $this->assertStringContainsString('Laundry Berkah Utama', $res['reply']);
        $this->assertStringContainsString('menerima 2 pesanan', $res['reply']);
        $this->assertStringContainsString('1 selesai, 1 diproses, 0 dibatalkan', $res['reply']);
        $this->assertStringContainsString('Hari ini ada 1 pesanan masuk', $res['reply']);
    }

    /**
     * Membuktikan perhitungan "hari ini" benar:
     * Pesanan kemarin (meskipun baris DB dibuat hari ini oleh Eloquent) TIDAK boleh masuk hitungan hari ini.
     */
    public function test_today_orders_calculation_strictly_excludes_yesterday_orders(): void
    {
        $owner = User::factory()->create(['role' => 'store_admin', 'is_active' => true]);
        $store = Store::factory()->create(['owner_user_id' => $owner->id, 'name' => 'Laundry Express Hari Ini']);
        $tz = config('app.timezone', 'UTC');

        // 2 pesanan kemarin (order_date kemarin dan 2 hari lalu)
        Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::COMPLETED->value,
            'order_date' => Carbon::now('Asia/Jakarta')->subDays(2)->setTimezone($tz),
        ]);

        Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::COMPLETED->value,
            'order_date' => Carbon::now('Asia/Jakarta')->subDay()->startOfDay()->addHours(12)->setTimezone($tz),
        ]);

        // 1 pesanan hari ini di zona WIB
        Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::PROCESSING->value,
            'order_date' => Carbon::now('Asia/Jakarta')->startOfDay()->addHours(2)->setTimezone($tz),
        ]);

        $res = $this->service->chat($owner, null, 'pesanan toko hari ini');
        $this->assertCount(0, $this->client->calls);
        $this->assertStringContainsString('menerima 3 pesanan', $res['reply']);
        $this->assertStringContainsString('Hari ini ada 1 pesanan masuk', $res['reply'], 'Pesanan kemarin tidak boleh dihitung sebagai pesanan hari ini!');
    }

    /**
     * Membuktikan batas awal dan akhir hari WIB dikonversi dengan benar ke zona penyimpanan data (UTC):
     * - 00:30 WIB hari ini (17:30 UTC kemarin) -> HARUS MASUK
     * - 23:30 WIB hari ini (16:30 UTC hari ini) -> HARUS MASUK
     * - 23:30 WIB kemarin (16:30 UTC kemarin) -> HARUS DIKECUALIKAN
     * - 00:30 WIB besok (17:30 UTC hari ini) -> HARUS DIKECUALIKAN
     */
    public function test_today_orders_boundary_0030_wib_and_2330_wib(): void
    {
        $owner = User::factory()->create(['role' => 'store_admin', 'is_active' => true]);
        $store = Store::factory()->create(['owner_user_id' => $owner->id, 'name' => 'Laundry Boundary Test']);
        $storageTz = config('app.timezone', 'UTC');

        // 1. Pesanan kemarin pukul 23:30 WIB (harus dikecualikan!)
        $yesterday2330 = Carbon::now('Asia/Jakarta')->subDay()->startOfDay()->addHours(23)->addMinutes(30)->setTimezone($storageTz);
        Order::factory()->create([
            'store_id' => $store->id,
            'order_number' => 'ORD-YEST-2330',
            'status' => OrderStatus::COMPLETED->value,
            'order_date' => $yesterday2330,
            'created_at' => $yesterday2330,
        ]);

        // 2. Pesanan hari ini pukul 00:30 WIB (harus masuk!)
        $today0030 = Carbon::now('Asia/Jakarta')->startOfDay()->addMinutes(30)->setTimezone($storageTz);
        Order::factory()->create([
            'store_id' => $store->id,
            'order_number' => 'ORD-TODAY-0030',
            'status' => OrderStatus::PROCESSING->value,
            'order_date' => $today0030,
            'created_at' => $today0030,
        ]);

        // 3. Pesanan hari ini pukul 23:30 WIB (harus masuk!)
        $today2330 = Carbon::now('Asia/Jakarta')->startOfDay()->addHours(23)->addMinutes(30)->setTimezone($storageTz);
        Order::factory()->create([
            'store_id' => $store->id,
            'order_number' => 'ORD-TODAY-2330',
            'status' => OrderStatus::PROCESSING->value,
            'order_date' => $today2330,
            'created_at' => $today2330,
        ]);

        // 4. Pesanan besok pukul 00:30 WIB (harus dikecualikan!)
        $tomorrow0030 = Carbon::now('Asia/Jakarta')->addDay()->startOfDay()->addMinutes(30)->setTimezone($storageTz);
        Order::factory()->create([
            'store_id' => $store->id,
            'order_number' => 'ORD-TOMO-0030',
            'status' => OrderStatus::PROCESSING->value,
            'order_date' => $tomorrow0030,
            'created_at' => $tomorrow0030,
        ]);

        $res = $this->service->chat($owner, null, 'pesanan toko hari ini');
        $this->assertCount(0, $this->client->calls);
        $this->assertStringContainsString('menerima 4 pesanan', $res['reply']);
        $this->assertStringContainsString('Hari ini ada 2 pesanan masuk', $res['reply'], 'Hanya pesanan pukul 00:30 WIB dan 23:30 WIB yang boleh dihitung hari ini!');
    }

    /**
     * Perilaku untuk pemilik / admin toko:
     * - "berapa kali saya memesan" -> pesanan sebagai pelanggan.
     * - "berapa pesanan masuk toko saya" / "pesanan toko hari ini" -> statistik toko.
     * - Ambigu ("berapa pesanan saya") -> menyebutkan keduanya dengan ringkas.
     */
    public function test_store_owner_personal_orders_vs_store_orders_and_ambiguous_query(): void
    {
        $owner = User::factory()->create(['role' => 'store_admin', 'is_active' => true]);
        $store = Store::factory()->create(['owner_user_id' => $owner->id, 'name' => 'Laundry Berkah Hendra']);

        $tz = config('app.timezone', 'UTC');
        // Toko menerima 2 pesanan masuk
        Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::COMPLETED->value,
            'order_date' => Carbon::now('Asia/Jakarta')->subDays(2)->setTimezone($tz),
            'created_at' => Carbon::now('Asia/Jakarta')->subDays(2)->setTimezone($tz),
        ]);
        Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::PROCESSING->value,
            'order_date' => Carbon::now('Asia/Jakarta')->startOfDay()->addHour()->setTimezone($tz),
            'created_at' => Carbon::now('Asia/Jakarta')->startOfDay()->addHour()->setTimezone($tz),
        ]);

        // Kasus A: Store owner belum pernah memesan sebagai pelanggan
        $resCustZero = $this->service->chat($owner, null, 'berapa kali saya memesan');
        $this->assertEquals('Anda belum memiliki pesanan di LaundryKu.', $resCustZero['reply']);

        // Kasus B: Store owner bertanya statistik toko
        $resStore = $this->service->chat($owner, null, 'berapa pesanan masuk toko saya');
        $this->assertStringContainsString('Laundry Berkah Hendra', $resStore['reply']);
        $this->assertStringContainsString('menerima 2 pesanan', $resStore['reply']);
        $this->assertStringContainsString('Hari ini ada 1 pesanan masuk', $resStore['reply']);

        // Kasus C: Pertanyaan ambigu "berapa pesanan saya" -> menyebutkan keduanya secara ringkas
        $resAmbiguous = $this->service->chat($owner, null, 'berapa pesanan saya');
        $this->assertStringContainsString('Sebagai pelanggan: Anda belum memiliki pesanan di LaundryKu.', $resAmbiguous['reply']);
        $this->assertStringContainsString('Toko yang Anda kelola (Laundry Berkah Hendra)', $resAmbiguous['reply']);
        $this->assertStringContainsString('menerima 2 pesanan', $resAmbiguous['reply']);

        // Kasus D: Sekarang store owner membuat 1 pesanan sebagai pelanggan di toko lain
        $otherStore = Store::factory()->create();
        Order::factory()->create([
            'customer_id' => $owner->id,
            'store_id' => $otherStore->id,
            'status' => OrderStatus::COMPLETED->value,
            'order_date' => Carbon::now('Asia/Jakarta')->subDay()->setTimezone($tz),
            'created_at' => Carbon::now('Asia/Jakarta')->subDay()->setTimezone($tz),
        ]);

        $resCustOne = $this->service->chat($owner, null, 'berapa kali saya memesan');
        $this->assertEquals('Anda sudah memesan 1 kali. 1 selesai, 0 diproses, 0 dibatalkan.', $resCustOne['reply']);
    }

    /**
     * 4. Kurir: memakai relasi User -> Courier -> CourierTask (tidak berasumsi courier_id adalah user_id).
     */
    public function test_courier_statistics_using_courier_profile_and_tasks(): void
    {
        $courierUser = User::factory()->create(['role' => 'courier', 'is_active' => true]);
        $courierProfile = Courier::create([
            'user_id' => $courierUser->id,
            'vehicle_type' => 'motorcycle',
            'is_verified' => true,
            'is_available' => true,
        ]);

        $order = Order::factory()->create();

        // 1 pickup task (delivered/selesai)
        CourierTask::create([
            'order_id' => $order->id,
            'courier_id' => $courierProfile->id,
            'task_type' => 'pickup',
            'status' => 'delivered',
        ]);

        // 1 delivery task (assigned/berjalan)
        CourierTask::create([
            'order_id' => $order->id,
            'courier_id' => $courierProfile->id,
            'task_type' => 'delivery',
            'status' => 'assigned',
        ]);

        $res = $this->service->chat($courierUser, null, 'tugas kurir saya berapa');
        $this->assertCount(0, $this->client->calls);
        $this->assertStringContainsString('total 2 tugas kurir', $res['reply']);
        $this->assertStringContainsString('1 penjemputan, 1 pengantaran', $res['reply']);
        $this->assertStringContainsString('1 selesai, 1 sedang berjalan, 0 dibatalkan/gagal', $res['reply']);
    }

    /**
     * 5. Super Admin / Platform: ringkasan platform keseluruhan.
     */
    public function test_super_admin_platform_overview(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        Order::factory()->create(['status' => OrderStatus::COMPLETED->value]);
        Order::factory()->create(['status' => OrderStatus::WAITING_PICKUP->value]);

        $res = $this->service->chat($admin, null, 'bagaimana ringkasan pesanan platform');
        $this->assertCount(0, $this->client->calls);
        $this->assertStringContainsString('Ringkasan platform LaundryKu: total 2 pesanan', $res['reply']);
        $this->assertStringContainsString('1 selesai, 1 diproses, 0 dibatalkan', $res['reply']);
    }

    /**
     * 6. Tamu (Unauthenticated): diarahkan untuk masuk terlebih dahulu.
     */
    public function test_guest_asking_order_stats_is_directed_to_login(): void
    {
        $guestRes = $this->service->replyForGuest('berapa kali saya memesan');
        $this->assertStringContainsString('/auth', $guestRes['answer']);
        $this->assertStringContainsString('masuk ke akun Anda terlebih dahulu', $guestRes['answer']);
    }

    /**
     * 7. Tes Isolasi Data: statistik Pelanggan A tidak pernah memuat pesanan Pelanggan B,
     * dan Toko A tidak pernah memuat pesanan Toko B.
     */
    public function test_multi_tenant_data_isolation(): void
    {
        $customerA = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $customerB = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        $ownerA = User::factory()->create(['role' => 'store_admin', 'is_active' => true]);
        $ownerB = User::factory()->create(['role' => 'store_admin', 'is_active' => true]);

        $storeA = Store::factory()->create(['owner_user_id' => $ownerA->id, 'name' => 'Store Alpha']);
        $storeB = Store::factory()->create(['owner_user_id' => $ownerB->id, 'name' => 'Store Beta']);

        // Customer A memesan di Store A (1 pesanan)
        Order::factory()->create([
            'customer_id' => $customerA->id,
            'store_id' => $storeA->id,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => 'paid',
            'total_amount' => 50000,
        ]);

        // Customer B memesan di Store B (3 pesanan)
        Order::factory()->count(3)->create([
            'customer_id' => $customerB->id,
            'store_id' => $storeB->id,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => 'paid',
            'total_amount' => 100000,
        ]);

        // Verifikasi Customer A hanya melihat 1 pesanannya sendiri
        $resA = $this->service->chat($customerA, null, 'berapa kali saya memesan');
        $this->assertEquals('Anda sudah memesan 1 kali. 1 selesai, 0 diproses, 0 dibatalkan.', $resA['reply']);

        $resASpend = $this->service->chat($customerA, null, 'total pengeluaran saya');
        $this->assertStringContainsString('Rp 50.000', $resASpend['reply']);

        // Verifikasi Customer B hanya melihat 3 pesanannya sendiri
        $resB = $this->service->chat($customerB, null, 'berapa kali saya memesan');
        $this->assertEquals('Anda sudah memesan 3 kali. 3 selesai, 0 diproses, 0 dibatalkan.', $resB['reply']);

        $resBSpend = $this->service->chat($customerB, null, 'total pengeluaran saya');
        $this->assertStringContainsString('Rp 300.000', $resBSpend['reply']);

        // Verifikasi Store A hanya menghitung 1 pesanan milik Store A
        $resStoreA = $this->service->chat($ownerA, null, 'pesanan toko saya berapa');
        $this->assertStringContainsString('Store Alpha', $resStoreA['reply']);
        $this->assertStringContainsString('menerima 1 pesanan', $resStoreA['reply']);

        // Verifikasi Store B hanya menghitung 3 pesanan milik Store B
        $resStoreB = $this->service->chat($ownerB, null, 'pesanan toko saya berapa');
        $this->assertStringContainsString('Store Beta', $resStoreB['reply']);
        $this->assertStringContainsString('menerima 3 pesanan', $resStoreB['reply']);
    }

    /**
     * Membuktikan konversi tampilan dari zona penyimpanan (UTC) ke WIB sudah benar:
     * Pesanan dengan order_date 10:00 UTC ditampilkan sebagai 17:00 WIB.
     */
    public function test_last_order_displays_correct_wib_converted_from_utc(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $store = Store::factory()->create();

        // Pesanan dengan order_date 10:00:00 UTC
        Order::factory()->create([
            'order_number' => 'ORD-10UTC-PROOF',
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => OrderStatus::PROCESSING->value,
            'order_date' => '2026-10-18 10:00:00',
            'created_at' => '2026-10-18 10:00:00',
        ]);

        $res = $this->service->chat($customer, null, 'pesanan terakhir saya');
        $this->assertCount(0, $this->client->calls);
        $this->assertStringContainsString('ORD-10UTC-PROOF', $res['reply']);
        $this->assertStringContainsString('18 Oktober 2026, 17:00 WIB', $res['reply'], '10:00 UTC harus dikonversi dengan tepat menjadi 17:00 WIB!');
    }
}
