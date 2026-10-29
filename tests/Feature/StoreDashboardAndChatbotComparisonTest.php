<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Ai\AiClientInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreDashboardAndChatbotComparisonTest extends TestCase
{
    use RefreshDatabase;

    protected User $storeAdminA;
    protected Store $storeA;
    protected User $storeAdminB;
    protected Store $storeB;
    protected User $customer;
    protected Courier $courierA1;
    protected Courier $courierA2;
    protected Courier $courierB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // Fake AI client to guarantee no external API calls
        $this->app->instance(AiClientInterface::class, new class implements AiClientInterface {
            public function chat(array $messages, array $tools): array
            {
                return [
                    'content' => 'Jawaban AI fallback.',
                    'tool_calls' => [],
                    'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5],
                ];
            }
        });

        // Customer
        $this->customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'name' => 'Budi Santoso',
            'is_active' => true,
        ]);

        // Store Admin A & Store A
        $this->storeAdminA = User::factory()->create([
            'role' => User::ROLE_STORE_ADMIN,
            'name' => 'Arif Wicaksono',
            'email' => 'arif@laundryberkah.com',
            'phone' => '081234567890',
            'is_active' => true,
        ]);
        $storeAdminRole = Role::where('name', 'store_admin')->first();
        if ($storeAdminRole) {
            $this->storeAdminA->roles()->attach($storeAdminRole->id);
        }

        $this->storeA = Store::factory()->create([
            'owner_user_id' => $this->storeAdminA->id,
            'name' => 'Berkah Laundry Express',
            'status' => 'approved',
            'is_active' => true,
            'address' => 'Jl. Mawar No. 10, Jakarta Selatan',
        ]);

        // Store Admin B & Store B (for cross-store isolation tests)
        $this->storeAdminB = User::factory()->create([
            'role' => User::ROLE_STORE_ADMIN,
            'name' => 'Bambang Sudirman',
            'email' => 'bambang@laundrysukses.com',
            'is_active' => true,
        ]);
        if ($storeAdminRole) {
            $this->storeAdminB->roles()->attach($storeAdminRole->id);
        }

        $this->storeB = Store::factory()->create([
            'owner_user_id' => $this->storeAdminB->id,
            'name' => 'Sukses Laundry B',
            'status' => 'approved',
            'is_active' => true,
            'address' => 'Jl. Melati No. 20, Bandung',
        ]);

        // Couriers for Store A (1 active, 1 inactive -> total 2)
        $courierUserA1 = User::factory()->create(['role' => User::ROLE_COURIER, 'name' => 'Kurir Doni']);
        $this->courierA1 = Courier::factory()->create([
            'user_id' => $courierUserA1->id,
            'store_id' => $this->storeA->id,
            'is_available' => true,
        ]);

        $courierUserA2 = User::factory()->create(['role' => User::ROLE_COURIER, 'name' => 'Kurir Eko']);
        $this->courierA2 = Courier::factory()->create([
            'user_id' => $courierUserA2->id,
            'store_id' => $this->storeA->id,
            'is_available' => false,
        ]);

        // Courier for Store B
        $courierUserB = User::factory()->create(['role' => User::ROLE_COURIER, 'name' => 'Kurir Fajar']);
        $this->courierB = Courier::factory()->create([
            'user_id' => $courierUserB->id,
            'store_id' => $this->storeB->id,
            'is_available' => true,
        ]);

        // Seed Orders for Store A:
        // 1. New Orders (2 orders: pending_pickup)
        Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-A-NEW-01',
            'status' => 'pending_pickup',
            'total_amount' => 25000,
            'created_at' => now()->subHours(5),
        ]);
        Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-A-NEW-02',
            'status' => 'pending_pickup',
            'total_amount' => 35000,
            'created_at' => now()->subHours(2),
        ]);

        // 2. Processing Orders (3 orders: received, weighed, processing)
        Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-A-PROC-01',
            'status' => 'received',
            'total_amount' => 40000,
        ]);
        Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-A-PROC-02',
            'status' => 'weighed',
            'total_amount' => 45000,
        ]);
        Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-A-PROC-03',
            'status' => 'processing',
            'total_amount' => 50000,
        ]);

        // 3. Ready for Delivery (1 ready_for_delivery, 1 out_for_delivery -> total ready_for_delivery = 2)
        Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-A-READY-01',
            'status' => 'ready_for_delivery',
            'total_amount' => 30000,
        ]);
        Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-A-OUT-01',
            'status' => 'out_for_delivery',
            'total_amount' => 55000,
        ]);

        // 4. Completed Today (3 orders completed today, total amount = 120.000)
        Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-A-DONE-01',
            'status' => 'completed',
            'total_amount' => 40000,
            'completed_at' => now()->startOfDay()->addHours(9),
        ]);
        Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-A-DONE-02',
            'status' => 'completed',
            'total_amount' => 40000,
            'completed_at' => now()->startOfDay()->addHours(10),
        ]);
        Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-A-DONE-03',
            'status' => 'completed',
            'total_amount' => 40000,
            'completed_at' => now()->startOfDay()->addHours(11),
        ]);

        // Seed Orders for Store B (must never leak to Store A):
        Order::factory()->create([
            'store_id' => $this->storeB->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-B-SECRET-99',
            'status' => 'completed',
            'total_amount' => 999999,
            'completed_at' => now(),
        ]);
    }

    /**
     * PRIORITAS 1: Endpoint dashboard return 200 dengan semua key yang dipakai frontend.
     */
    public function test_store_dashboard_returns_200_with_all_frontend_keys(): void
    {
        Sanctum::actingAs($this->storeAdminA, ['role:store_admin']);

        $res = $this->getJson('/api/v1/store/dashboard');

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'store' => [
                        'id',
                        'name',
                        'approval_status',
                        'operational_status',
                        'rejection_reason',
                    ],
                    'metrics' => [
                        'new_orders',
                        'processing_orders',
                        'ready_for_delivery',
                        'completed_today',
                        'today_revenue',
                    ],
                    'recent_orders' => [
                        '*' => [
                            'id',
                            'order_number',
                            'customer_name',
                            'status',
                            'status_label',
                            'total_amount',
                            'created_at',
                        ],
                    ],
                ],
            ]);

        $metrics = $res->json('data.metrics');
        $this->assertSame(2, $metrics['new_orders'], 'New orders count mismatch');
        $this->assertSame(3, $metrics['processing_orders'], 'Processing orders count mismatch');
        $this->assertSame(2, $metrics['ready_for_delivery'], 'Ready for delivery count mismatch');
        $this->assertSame(3, $metrics['completed_today'], 'Completed today count mismatch');
        $this->assertEquals(120000.0, (float) $metrics['today_revenue'], 'Today revenue mismatch');

        $store = $res->json('data.store');
        $this->assertEquals((string) $this->storeA->id, $store['id']);
        $this->assertEquals('Berkah Laundry Express', $store['name']);
        $this->assertEquals('approved', $store['approval_status']);
        $this->assertEquals('active', $store['operational_status']);
    }

    /**
     * PRIORITAS 2: Chatbot menjawab isi dashboard dengan angka SAMA PERSIS dan isolasi tenant.
     */
    public function test_chatbot_answers_match_dashboard_metrics_and_isolate_other_stores(): void
    {
        Sanctum::actingAs($this->storeAdminA, ['role:store_admin']);

        // 1. Ambil data aktual dashboard sebagai patokan kebenaran (ground truth)
        $dashRes = $this->getJson('/api/v1/store/dashboard');
        $dashRes->assertStatus(200);
        $dashData = $dashRes->json('data');
        $m = $dashData['metrics'];

        // 2. Buat sesi percakapan chatbot baru
        $sessionRes = $this->postJson('/api/v1/chatbot/sessions', [
            'title' => 'Tes Asisten Dashboard Toko',
        ]);
        $sessionRes->assertStatus(201);
        $sessionId = $sessionRes->json('data.id');

        // Helper untuk kirim pesan dan ambil balasan
        $ask = function (string $question) use ($sessionId) {
            $msgRes = $this->postJson("/api/v1/chatbot/sessions/{$sessionId}/messages", [
                'message' => $question,
            ]);
            $msgRes->assertStatus(200);
            return $msgRes->json('data.assistant_message.content');
        };

        // --- A. RINGKASAN / KONDISI TOKO ---
        $summaryReply = $ask('ringkasan hari ini');
        $this->assertStringContainsString((string) $m['new_orders'], $summaryReply);
        $this->assertStringContainsString((string) $m['processing_orders'], $summaryReply);
        $this->assertStringContainsString((string) $m['ready_for_delivery'], $summaryReply);
        $this->assertStringContainsString((string) $m['completed_today'], $summaryReply);
        $this->assertStringContainsString('120.000', $summaryReply);
        $this->assertStringContainsString('1 aktif dari total 2', $summaryReply);
        $this->assertStringContainsString('Berkah Laundry Express', $summaryReply);
        $this->assertStringNotContainsString('Sukses Laundry B', $summaryReply);

        // Rekap slang
        $rekapReply = $ask('rekap');
        $this->assertStringContainsString('120.000', $rekapReply);

        // Dashboard gimana
        $dashGimanaReply = $ask('dashboard gimana');
        $this->assertStringContainsString((string) $m['new_orders'], $dashGimanaReply);

        // --- B. KARTU 1: PESANAN MASUK / BARU (Standard & Slang) ---
        $newOrderReply = $ask('pesanan masuk berapa');
        $this->assertStringContainsString((string) $m['new_orders'] . ' pesanan baru', $newOrderReply);

        $newOrderSlang = $ask('psnan msk brp');
        $this->assertStringContainsString((string) $m['new_orders'] . ' pesanan baru', $newOrderSlang);

        // --- C. KARTU 2: SEDANG DIPROSES (Standard & Slang) ---
        $procReply = $ask('yg lagi diproses brp');
        $this->assertStringContainsString((string) $m['processing_orders'] . ' pesanan cucian', $procReply);

        $procSlang = $ask('yg lg dproses brp');
        $this->assertStringContainsString((string) $m['processing_orders'] . ' pesanan cucian', $procSlang);

        // --- D. KARTU 3: SIAP DIANTAR (Standard & Slang) ---
        $readyReply = $ask('siap diantar');
        $this->assertStringContainsString((string) $m['ready_for_delivery'] . ' pesanan', $readyReply);

        $readySlang = $ask('siap anter');
        $this->assertStringContainsString((string) $m['ready_for_delivery'] . ' pesanan', $readySlang);

        // --- E. SEDANG DIANTAR / LAGI DIANTAR ---
        $outReply = $ask('lagi diantar');
        $this->assertStringContainsString('1 pesanan yang saat ini sedang dalam perjalanan', $outReply);

        // --- F. KARTU 4: SELESAI HARI INI ---
        $doneReply = $ask('selesai hari ini');
        $this->assertStringContainsString((string) $m['completed_today'] . ' pesanan yang telah selesai', $doneReply);

        // --- G. KARTU 5: OMZET HARI INI (Standard & Slang) ---
        $revReply = $ask('omzet');
        $this->assertStringContainsString('Rp 120.000', $revReply);

        $revSlang = $ask('omset hr ini');
        $this->assertStringContainsString('Rp 120.000', $revSlang);

        // --- H. KURIR AKTIF & TOTAL KURIR ---
        $courierReply = $ask('kurir aktif');
        $this->assertStringContainsString('total 2 kurir', $courierReply);
        $this->assertStringContainsString('1 kurir aktif', $courierReply);

        // --- I. DAFTAR PESANAN MASUK ---
        $listReply = $ask('daftar pesanan masuk');
        $this->assertStringContainsString('ORD-A-NEW-01', $listReply);
        $this->assertStringContainsString('ORD-A-NEW-02', $listReply);
        $this->assertStringNotContainsString('ORD-B-SECRET-99', $listReply, 'Data Toko B bocor ke Toko A!');

        // --- J. ANTREAN TERLAMA (PALING LAMA NUNGGU) ---
        $oldestReply = $ask('yg paling lama nunggu');
        $this->assertStringContainsString('ORD-A-NEW-01', $oldestReply);
        $this->assertStringContainsString('Rp 25.000', $oldestReply);

        // --- K. STATUS ORD-xxxx TOKO SENDIRI ---
        $ordReply = $ask('status ORD-A-NEW-01');
        $this->assertStringContainsString('ORD-A-NEW-01', $ordReply);
        $this->assertStringContainsString('Menunggu Penjemputan', $ordReply);
        $this->assertStringContainsString('Rp 25.000', $ordReply);

        // --- L. ISOLASI TENANT: STATUS ORD MILIK TOKO LAIN TIDAK BOLEH MUNCUL ---
        $ordSecretReply = $ask('status ORD-B-SECRET-99');
        $this->assertStringContainsString('tidak ditemukan pada toko yang Anda kelola', $ordSecretReply);
        $this->assertStringNotContainsString('999999', $ordSecretReply);

        // --- M. MENU DASHBOARD & AKSI ---
        $menuReply = $ask('menu apa aja');
        $this->assertStringContainsString('/store/orders', $menuReply);
        $this->assertStringContainsString('/store/services', $menuReply);
        $this->assertStringContainsString('/store/settings', $menuReply);

        $serviceGuide = $ask('tambah layanan di mana');
        $this->assertStringContainsString('/store/services', $serviceGuide);

        $courierGuide = $ask('cara assign kurir');
        $this->assertTrue(
            str_contains($courierGuide, '/store/orders') || str_contains($courierGuide, '/store/couriers'),
            "Expected courier guide to reference store orders or couriers menu. Actual: {$courierGuide}"
        );

        // --- N. IDENTITAS (SIAPA SAYA & TOKO SAYA APA) ---
        $whoAmIReply = $ask('siapa saya');
        $this->assertStringContainsString('Arif Wicaksono', $whoAmIReply);
        $this->assertStringContainsString('Berkah Laundry Express', $whoAmIReply);

        $myStoreReply = $ask('toko saya apa');
        $this->assertStringContainsString('Berkah Laundry Express', $myStoreReply);
        $this->assertStringContainsString('Jl. Mawar No. 10', $myStoreReply);
    }
}
