<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiIntentRouter;
use App\Services\Ai\AiTextNormalizer;
use App\Services\Ai\AiToolRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use App\Services\Ai\AiClientInterface;
use App\Services\Ai\AiProviderException;
use Tests\TestCase;

class DiagnosisFakeAiClient implements AiClientInterface
{
    public function chat(array $messages, array $tools): array
    {
        throw new AiProviderException('zero llm network simulated');
    }
}

class AiDiagnosisEvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected User $storeAdmin;
    protected User $superAdmin;
    protected Store $store;
    protected AiIntentRouter $router;
    protected AiAssistantService $assistant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SuperAdminSeeder::class);

        // Store Admin User & Store
        $this->storeAdmin = User::factory()->create([
            'name' => 'Budi Pemilik Toko',
            'email' => 'mitra_diag@laundryku.com',
            'role' => User::ROLE_STORE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $saRole = Role::where('name', 'store_admin')->first();
        if ($saRole) {
            $this->storeAdmin->roles()->attach($saRole->id, ['assigned_at' => now()]);
        }

        $this->store = Store::factory()->create([
            'owner_user_id' => $this->storeAdmin->id,
            'name' => 'Berkah Laundry Baros',
            'address' => 'Jl. Baros No. 12',
            'city' => 'Kota Cimahi',
            'province' => 'Jawa Barat',
            'latitude' => -6.885600,
            'longitude' => 107.543200,
            'service_radius_km' => 7.50,
            'status' => 'approved',
            'is_active' => true,
        ]);

        // Super Admin User
        $this->superAdmin = User::where('role', User::ROLE_SUPER_ADMIN)->first()
            ?? User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);

        $this->router = app(AiIntentRouter::class);
        $fakeClient = new DiagnosisFakeAiClient();
        $this->assistant = new AiAssistantService($fakeClient, new AiToolRegistry());
    }

    public function test_run_diagnosis_evaluation(): void
    {
        $storeAdminQueries = [
            // Omzet & Keuangan (10)
            'Berapa omzet toko saya hari ini?',
            'Omset kmrn dpt brp?',
            'Total pendapatan minggu ini berapa ya?',
            'Rekap omzet bulan ini',
            'Berapa pemasukan toko hari ini dibanding kemarin?',
            'omset toko hr ini brp min',
            'Pendapatan toko bulan ini berapa?',
            'uang masuk hari ini berapa?',
            'rekap duit masuk toko kmrn',
            'omzet bersih toko minggu ini',

            // Status Pesanan & Tindakan (12)
            'Ada berapa pesanan yang belum ditimbang?',
            'Cucian yg blm ditimbang ada brp?',
            'Pesanan yang siap diantar ada berapa?',
            'Berapa orderan yang lagi dicuci?',
            'Daftar cucian yang perlu diproses',
            'Ada berapa orderan baru masuk?',
            'pesanan nunggu jemput brp?',
            'Status orderan toko saat ini apa aja?',
            'Orderan yang butuh tindakan hari ini',
            'Berapa pesanan selesai hari ini?',
            'orderan yg blm kelar ada brp?',
            'brp pesanan msh diproses?',

            // Pesanan Spesifik (5)
            'Status pesanan ORD-20261024-0001 gimana?',
            'Cek order ORD-20261024-0002',
            'ORD-20261024-0003 udah dibayar belum?',
            'Detail cucian ORD-20261024-0004',
            'cek pesanan ORD-20261024-0005',

            // Layanan & Harga (8)
            'Daftar layanan dan tarif di toko saya apa aja?',
            'Berapa harga cuci kiloan di toko saya?',
            'Layanan apa yang paling laris di toko?',
            'Menu servis toko saya apa aja min?',
            'Layanan paling laku bulan ini apa?',
            'list harga toko sy',
            'tarif setrika di toko kita berapa?',
            'paket cuci apa yg paling banyak dipesan?',

            // Ulasan & Komplain (8)
            'Berapa rating toko saya saat ini?',
            'Ada ulasan atau bintang baru dari pelanggan?',
            'Apakah ada komplain aktif di toko saya?',
            'Komplain pelanggan yang belum selesai ada berapa?',
            'rating toko sy brp min?',
            'ada keluhan pelanggan gak hari ini?',
            'bintang toko saya berapa?',
            'toko saya dapet review apa aja?',

            // Kurir Toko (7)
            'Siapa saja kurir yang terikat di toko saya?',
            'Kurir toko saya siapa aja?',
            'Berapa jumlah kurir aktif toko saat ini?',
            'Ada berapa pelamar kurir yang daftar?',
            'daftar kandidat kurir toko',
            'kurir yg lagi online siapa aja?',
            'pelamar kurir yg blm direspon ada brp?',

            // Jam Operasional & Pengaturan Toko (8)
            'Jam operasional toko saya dari jam berapa sampai jam berapa?',
            'Jadwal buka toko hari ini gimana?',
            'Berapa radius jangkauan layanan toko saya?',
            'Radius toko saya berapa km?',
            'Alamat toko saya terdaftar di mana?',
            'Apakah toko saya sudah disetujui admin?',
            'Status approval gerai saya apa?',
            'jam buka toko saya hari minggu tutup gak?',

            // Top Customer (3)
            'Siapa pelanggan yang paling sering mencuci di toko saya?',
            'Top customer toko saya siapa?',
            'Pelanggan paling loyal di toko',

            // Panduan Prosedural (8)
            'Bagaimana cara mengubah jam operasional toko?',
            'Cara mengatur radius layanan toko gimana?',
            'Gimana cara menambah layanan dan harga baru?',
            'Cara menimbang cucian pelanggan di aplikasi',
            'Panduan memproses pesanan masuk',
            'Cara menugaskan kurir antar jemput',
            'Bagaimana cara menangani komplain pelanggan?',
            'Cara edit profil dan alamat gerai di settings',
        ];

        $superAdminQueries = [
            // Toko & Mitra (6)
            'Berapa jumlah toko mitra yang aktif di platform?',
            'Ada berapa pendaftaran toko yang menunggu persetujuan?',
            'Toko pending approval ada berapa?',
            'Jumlah toko terdaftar di seluruh sistem',
            'toko aktif se platform brp?',
            'mitra baru yg blm diapprove ada brp?',

            // Transaksi & Omzet Platform (6)
            'Berapa total pesanan di seluruh platform hari ini?',
            'Total transaksi platform minggu ini',
            'Berapa total omzet platform yang sudah dibayar?',
            'Rekap volume pesanan se-platform',
            'omset platform bulan ini brp?',
            'total transaksi sukses di sistem',

            // Komplain Platform (4)
            'Berapa jumlah komplain aktif di seluruh sistem?',
            'Ada berapa dispute pelanggan yang belum selesai?',
            'Rekap komplain terbuka di platform',
            'komplain yg blm kelar se platform',

            // Pengguna per Peran (5)
            'Berapa total pengguna terdaftar per peran?',
            'Jumlah pelanggan, mitra, dan kurir di sistem',
            'Total user aktif platform ada berapa?',
            'brp total customer di laundryku?',
            'jumlah kurir terdaftar di platform',

            // Antrean Verifikasi (4)
            'Berapa pendaftaran kurir yang menunggu verifikasi?',
            'Antrean verifikasi mitra dan kurir ada berapa?',
            'pelamar kurir pending approval brp?',
            'verifikasi mitra toko yg pending',

            // Pencarian & Cek Spesifik (3)
            'Cari toko Berkah Laundry',
            'Cari user Budi Santoso',
            'Cek pesanan ORD-20261024-0001 di sistem',

            // Panduan Admin (2)
            'Bagaimana cara menyetujui pendaftaran mitra baru?',
            'Cara memonitor performa platform di admin panel',
        ];

        $results = [
            'store_admin' => [],
            'super_admin' => [],
        ];

        // 1. Evaluate Store Admin queries
        foreach ($storeAdminQueries as $query) {
            \App\Models\AiMessage::query()->delete();
            \App\Models\AiUsageLog::query()->delete();
            \App\Models\AiConversation::query()->delete();
            \Illuminate\Support\Facades\Cache::flush();
            $routing = $this->router->route($this->storeAdmin, $query);
            $layer = $routing['layer'];
            $intent = $routing['intent'];
            $confidence = $routing['confidence'];

            // Simulate assistant processing to see if it yields a valid local answer or fails
            $reply = null;
            $status = 'FAILED';
            $reason = '';

            try {
                $response = $this->assistant->chat($this->storeAdmin, null, $query);
                $reply = $response['reply'] ?? '';
                $fallback = $response['fallback'] ?? false;

                if ($layer === 'layer_1_rules' && !$fallback && !empty($reply) && !str_contains($reply, 'belum memahami maksud')) {
                    $status = 'SUCCESS';
                } else {
                    $status = 'FAILED';
                    if (str_contains($reply, 'tidak dapat')) {
                        $reason = 'ditolak_guardrail_mutasi';
                    } elseif ($layer !== 'layer_1_rules') {
                        $reason = $intent === 'unknown' ? 'tidak_ada_intent' : 'skor_di_bawah_ambang';
                    } elseif ($fallback) {
                        $reason = 'handler_mengembalikan_kosong_fallback';
                    } else {
                        $reason = 'jawaban_tidak_memadai';
                    }
                }
            } catch (\Throwable $e) {
                $status = 'FAILED';
                $reason = 'exception: ' . $e->getMessage();
            }

            $results['store_admin'][] = [
                'query' => $query,
                'intent' => $intent,
                'layer' => $layer,
                'confidence' => $confidence,
                'status' => $status,
                'reason' => $reason,
                'reply_sample' => mb_substr($reply ?? '', 0, 80),
            ];
        }

        // 2. Evaluate Super Admin queries
        foreach ($superAdminQueries as $query) {
            \App\Models\AiMessage::query()->delete();
            \App\Models\AiUsageLog::query()->delete();
            \App\Models\AiConversation::query()->delete();
            \Illuminate\Support\Facades\Cache::flush();
            $routing = $this->router->route($this->superAdmin, $query);
            $layer = $routing['layer'];
            $intent = $routing['intent'];
            $confidence = $routing['confidence'];

            $reply = null;
            $status = 'FAILED';
            $reason = '';

            try {
                $response = $this->assistant->chat($this->superAdmin, null, $query);
                $reply = $response['reply'] ?? '';
                $fallback = $response['fallback'] ?? false;

                if ($layer === 'layer_1_rules' && !$fallback && !empty($reply) && !str_contains($reply, 'belum memahami maksud')) {
                    $status = 'SUCCESS';
                } else {
                    $status = 'FAILED';
                    if (str_contains($reply, 'tidak dapat')) {
                        $reason = 'ditolak_guardrail_mutasi';
                    } elseif ($layer !== 'layer_1_rules') {
                        $reason = $intent === 'unknown' ? 'tidak_ada_intent' : 'skor_di_bawah_ambang';
                    } elseif ($fallback) {
                        $reason = 'handler_mengembalikan_kosong_fallback';
                    } else {
                        $reason = 'jawaban_tidak_memadai';
                    }
                }
            } catch (\Throwable $e) {
                $status = 'FAILED';
                $reason = 'exception: ' . $e->getMessage();
            }

            $results['super_admin'][] = [
                'query' => $query,
                'intent' => $intent,
                'layer' => $layer,
                'confidence' => $confidence,
                'status' => $status,
                'reason' => $reason,
                'reply_sample' => mb_substr($reply ?? '', 0, 80),
            ];
        }

        $testingDir = storage_path('framework/testing');
        if (!is_dir($testingDir)) {
            @mkdir($testingDir, 0777, true);
        }
        @file_put_contents(
            $testingDir . '/diagnosis_results.json',
            json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $storeSuccess = count(array_filter($results['store_admin'], fn ($r) => $r['status'] === 'SUCCESS'));
        $superSuccess = count(array_filter($results['super_admin'], fn ($r) => $r['status'] === 'SUCCESS'));

        $this->assertGreaterThanOrEqual(0.95, $storeSuccess / count($storeAdminQueries), 'Store admin Layer 1 accuracy must be at least 95%');
        $this->assertGreaterThanOrEqual(0.95, $superSuccess / count($superAdminQueries), 'Super admin Layer 1 accuracy must be at least 95%');
    }

    public function test_tenant_isolation_store_admin_a_cannot_see_store_b_data(): void
    {
        // Setup Store Admin B & Store B
        $storeAdminB = User::factory()->create([
            'name' => 'Doni Pemilik Toko B',
            'email' => 'storeb_owner@laundryku.com',
            'role' => User::ROLE_STORE_ADMIN,
        ]);
        $saRole = Role::where('name', 'store_admin')->first();
        if ($saRole) {
            $storeAdminB->roles()->attach($saRole->id, ['assigned_at' => now()]);
        }

        $storeB = Store::factory()->create([
            'owner_user_id' => $storeAdminB->id,
            'name' => 'Kilau Clean Toko B',
            'address' => 'Jl. Gegerkalong No. 45',
            'city' => 'Bandung',
            'status' => 'approved',
            'is_active' => true,
        ]);

        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        // Order in Store A: Rp 50.000
        $orderA = Order::factory()->create([
            'order_number' => 'ORD-TESTA-0001',
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 50000,
        ]);
        \App\Models\Payment::create([
            'order_id' => $orderA->id,
            'payment_method' => 'qris',
            'status' => 'paid',
            'amount' => 50000,
            'paid_at' => now(),
        ]);

        // Order in Store B: Rp 150.000
        $orderB = Order::factory()->create([
            'order_number' => 'ORD-TESTB-9999',
            'store_id' => $storeB->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 150000,
        ]);
        \App\Models\Payment::create([
            'order_id' => $orderB->id,
            'payment_method' => 'qris',
            'status' => 'paid',
            'amount' => 150000,
            'paid_at' => now(),
        ]);

        // 1. Store Admin A looking up Store B order number
        $resOrder = $this->assistant->chat($this->storeAdmin, null, 'Cek status pesanan ORD-TESTB-9999');
        $this->assertStringContainsString('tidak ditemukan pada toko yang Anda kelola', $resOrder['reply']);
        $this->assertStringNotContainsString('Kilau Clean', $resOrder['reply']);

        // 2. Store Admin A asking omzet
        $resOmzet = $this->assistant->chat($this->storeAdmin, null, 'Berapa omzet toko saya hari ini?');
        $this->assertStringContainsString('50.000', $resOmzet['reply']);
        $this->assertStringNotContainsString('150.000', $resOmzet['reply']);
        $this->assertStringNotContainsString('200.000', $resOmzet['reply']);
    }

    public function test_super_admin_does_not_receive_raw_pii_email_and_phone(): void
    {
        $sensitiveUser = User::factory()->create([
            'name' => 'Budi Rahasia Santoso',
            'email' => 'budi.rahasia123@example.com',
            'phone' => '081234567890',
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        // 1. User search by name
        $searchRes = $this->assistant->chat($this->superAdmin, null, 'Cari user Budi Rahasia');
        $reply = $searchRes['reply'];

        // Raw PII must NOT appear
        $this->assertStringNotContainsString('budi.rahasia123@example.com', $reply);
        $this->assertStringNotContainsString('081234567890', $reply);

        // Masked PII must appear
        $this->assertStringContainsString('bu***@example.com', $reply);
        $this->assertStringContainsString('0812****890', $reply);

        // 2. User count aggregation
        $countRes = $this->assistant->chat($this->superAdmin, null, 'Berapa total pengguna terdaftar per peran?');
        $countReply = $countRes['reply'];
        $this->assertStringContainsString('Pelanggan (Customer)', $countReply);
        $this->assertStringContainsString('Kurir', $countReply);
        $this->assertStringContainsString('Administrator Platform', $countReply);
        $this->assertStringNotContainsString('@', $countReply);
    }

    public function test_direct_mutation_commands_are_strictly_rejected(): void
    {
        // 1. Change operational hours
        $resHours = $this->assistant->chat($this->storeAdmin, null, 'ubah jam buka toko jadi jam 07:00');
        $this->assertStringContainsString('tidak dapat dilakukan melalui chat', $resHours['reply']);
        $this->assertStringContainsString('/store', $resHours['reply']);

        // 2. Change price
        $resPrice = $this->assistant->chat($this->storeAdmin, null, 'ganti harga cuci kiloan jadi 10000');
        $this->assertStringContainsString('tidak dapat dilakukan melalui chat', $resPrice['reply']);
        $this->assertStringContainsString('/store', $resPrice['reply']);

        // 3. Super admin approve store
        $resApprove = $this->assistant->chat($this->superAdmin, null, 'setujui toko mitra baru');
        $this->assertStringContainsString('tidak dapat dilakukan melalui chat', $resApprove['reply']);
        $this->assertStringContainsString('/admin', $resApprove['reply']);

        // 4. Cancel order
        $resCancel = $this->assistant->chat($this->storeAdmin, null, 'batalkan pesanan ORD-20261024-0001');
        $this->assertStringContainsString('tidak dapat membatalkan pesanan secara langsung', $resCancel['reply']);
        $this->assertStringContainsString('/orders', $resCancel['reply']);
    }

    public function test_procedural_how_to_questions_are_answered_as_guides_not_rejected_as_mutations(): void
    {
        // 1. Cara mengubah jam operasional
        $resHoursGuide = $this->assistant->chat($this->storeAdmin, null, 'Bagaimana cara mengubah jam operasional toko?');
        $this->assertStringNotContainsString('tidak dapat dilakukan melalui chat', $resHoursGuide['reply']);
        $this->assertStringContainsString('/store/settings', $resHoursGuide['reply']);

        // 2. Cara mengatur radius
        $resRadiusGuide = $this->assistant->chat($this->storeAdmin, null, 'Cara mengatur radius layanan toko gimana?');
        $this->assertStringNotContainsString('tidak dapat dilakukan melalui chat', $resRadiusGuide['reply']);
        $this->assertStringContainsString('/store/settings', $resRadiusGuide['reply']);

        // 3. Panduan menambah layanan
        $resServiceGuide = $this->assistant->chat($this->storeAdmin, null, 'Gimana cara menambah layanan dan harga baru?');
        $this->assertStringNotContainsString('tidak dapat dilakukan melalui chat', $resServiceGuide['reply']);
        $this->assertStringContainsString('/store/services', $resServiceGuide['reply']);

        // 4. Petunjuk timbang
        $resWeighGuide = $this->assistant->chat($this->storeAdmin, null, 'Cara menimbang cucian pelanggan di aplikasi');
        $this->assertStringNotContainsString('tidak dapat dilakukan melalui chat', $resWeighGuide['reply']);
        $this->assertStringContainsString('timbang', strtolower($resWeighGuide['reply']));
    }

    public function test_off_topic_questions_are_politely_rejected(): void
    {
        $resRendang = $this->assistant->chat($this->storeAdmin, null, 'Bagaimana cara memasak rendang daging sapi?');
        $this->assertStringContainsString('Maaf', $resRendang['reply']);
        $this->assertTrue(
            str_contains($resRendang['reply'], 'hanya dapat membantu') ||
            str_contains($resRendang['reply'], 'LaundryKu')
        );

        $resPresiden = $this->assistant->chat($this->storeAdmin, null, 'Siapa presiden indonesia saat ini?');
        $this->assertStringContainsString('Maaf', $resPresiden['reply']);

        $resCode = $this->assistant->chat($this->storeAdmin, null, 'Bikin script python untuk scrape website');
        $this->assertStringContainsString('Maaf', $resCode['reply']);
    }

    public function test_empty_store_data_returns_informative_actionable_response(): void
    {
        // Store with 0 orders
        $emptyStoreAdmin = User::factory()->create([
            'name' => 'Admin Toko Kosong',
            'email' => 'toko_kosong@laundryku.com',
            'role' => User::ROLE_STORE_ADMIN,
        ]);
        $saRole = Role::where('name', 'store_admin')->first();
        if ($saRole) {
            $emptyStoreAdmin->roles()->attach($saRole->id, ['assigned_at' => now()]);
        }

        Store::factory()->create([
            'owner_user_id' => $emptyStoreAdmin->id,
            'name' => 'Toko Laundry Perdana',
            'status' => 'approved',
            'is_active' => true,
        ]);

        $res = $this->assistant->chat($emptyStoreAdmin, null, 'Berapa omzet toko saya hari ini?');
        $this->assertStringContainsString('belum mencatatkan transaksi terbayar hari ini', $res['reply']);
        $this->assertStringContainsString('Saran tindakan', $res['reply']);
    }

    public function test_provider_fallback_returns_rich_role_tailored_clarification(): void
    {
        // Store admin asking unmapped in-domain query that falls through to provider and catches AiProviderException
        $res = $this->assistant->chat($this->storeAdmin, null, 'Bisa jelaskan sejarah berdirinya LaundryKu?');
        $this->assertTrue($res['fallback']);
        $this->assertStringContainsString('Sebagai pengelola toko mitra di LaundryKu', $res['reply']);
        $this->assertStringContainsString('Omzet & Keuangan', $res['reply']);
        $this->assertStringContainsString('Pesanan & Tindakan', $res['reply']);
        $this->assertStringContainsString('Pengaturan Gerai', $res['reply']);

        // Super admin asking unmapped in-domain query that falls through to provider and catches AiProviderException
        $resAdmin = $this->assistant->chat($this->superAdmin, null, 'Bagaimana arsitektur cloud server LaundryKu?');
        $this->assertTrue($resAdmin['fallback']);
        $this->assertStringContainsString('Sebagai admin platform LaundryKu', $resAdmin['reply']);
        $this->assertStringContainsString('Ringkasan Platform', $resAdmin['reply']);
        $this->assertStringContainsString('Persetujuan & Verifikasi', $resAdmin['reply']);
        $this->assertStringContainsString('Pengguna & Peran', $resAdmin['reply']);
    }
}
