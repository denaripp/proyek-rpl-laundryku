<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiUsageLog;
use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiClientInterface;
use App\Services\Ai\AiDataQueryService;
use App\Services\Ai\AiIntentRouter;
use App\Services\Ai\AiOrderStatsService;
use App\Services\Ai\AiTextNormalizer;
use App\Services\Ai\AiTopicGuardrail;
use App\Services\Ai\AiToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FakeParaphraseClient implements AiClientInterface
{
    public array $calls = [];
    public ?string $mockReply = null;

    public function chat(array $messages, array $tools = []): array
    {
        $this->calls[] = ['messages' => $messages, 'tools' => $tools];

        return [
            'content' => $this->mockReply ?? 'Jawaban simulasi model.',
            'tool_calls' => [],
            'usage' => ['prompt_tokens' => 25, 'completion_tokens' => 15],
        ];
    }
}

class AiParaphraseTest extends TestCase
{
    use RefreshDatabase;

    protected AiAssistantService $service;
    protected AiIntentRouter $router;
    protected AiTextNormalizer $normalizer;
    protected AiTopicGuardrail $guardrail;
    protected AiOrderStatsService $statsService;
    protected AiDataQueryService $dataService;
    protected FakeParaphraseClient $client;
    protected User $customer;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new FakeParaphraseClient();
        $this->normalizer = new AiTextNormalizer();
        $this->guardrail = new AiTopicGuardrail();
        $this->statsService = new AiOrderStatsService();
        $this->dataService = new AiDataQueryService($this->statsService);
        $this->router = new AiIntentRouter($this->normalizer, $this->client);

        $this->service = new AiAssistantService(
            $this->client,
            new AiToolRegistry(),
            $this->guardrail,
            $this->statsService,
            $this->dataService,
            $this->router
        );

        // Seed basic settings
        DB::table('settings')->updateOrInsert(['key' => 'COD_MAX_AMOUNT'], ['value' => '500000', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('settings')->updateOrInsert(['key' => 'DISPUTE_WINDOW_HOURS'], ['value' => '24', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('settings')->updateOrInsert(['key' => 'PAYMENT_WINDOW_HOURS'], ['value' => '1', 'created_at' => now(), 'updated_at' => now()]);

        $this->customer = User::factory()->create([
            'name' => 'Budi Paraphrase',
            'role' => 'customer',
            'is_active' => true,
        ]);

        $this->store = Store::factory()->create([
            'name' => 'Berkah Laundry Test',
            'status' => 'approved',
            'is_active' => true,
        ]);
    }

    /**
     * 1. Evaluasi set uji parafrasa komprehensif (>= 300 kalimat).
     * Membuktikan akurasi Lapisan 1 menangkap mayoritas dan total akurasi > 95%.
     */
    public function test_large_paraphrase_dataset_accuracy_exceeds_targets(): void
    {
        $datasetPath = dirname(__DIR__, 2) . '/storage/paraphrase_dataset.php';
        if (!file_exists($datasetPath)) {
            $datasetPath = 'C:/Users/jrsdi/.gemini/antigravity-ide/brain/7f43cdfb-e732-48a0-b668-42de7f9f6d2c/scratch/paraphrase_dataset.php';
        }

        $this->assertFileExists($datasetPath, 'File dataset parafrasa wajib tersedia.');
        $dataset = require $datasetPath;

        $totalQueries = count($dataset);
        $this->assertGreaterThanOrEqual(300, $totalQueries, 'Dataset parafrasa wajib memiliki minimal 300 kueri.');

        $layer1Captured = 0;
        $layer1Correct = 0;
        $failures = [];

        $roleUsers = [
            'customer' => $this->customer,
            'courier' => User::factory()->create(['role' => 'courier', 'is_active' => true]),
            'store_admin' => User::factory()->create(['role' => 'store_admin', 'is_active' => true]),
            'store_owner' => User::factory()->create(['role' => 'store_admin', 'is_active' => true]),
            'super_admin' => User::factory()->create(['role' => 'super_admin', 'is_active' => true]),
        ];

        foreach ($dataset as $idx => $item) {
            $query = $item['query'];
            $expected = $item['expected'];
            $roleKey = $item['role'] ?? 'customer';
            $user = $roleUsers[$roleKey] ?? $this->customer;

            $routing = $this->router->route($user, $query);

            if ($routing['layer'] === 'layer_1_rules') {
                $layer1Captured++;
            }

            $isExpectedMatch = ($routing['intent'] === $expected)
                || ($expected === 'procedural_guide' && (str_starts_with($routing['intent'], 'guide_') || $routing['intent'] === 'procedural_guide'));

            if ($isExpectedMatch) {
                if ($routing['layer'] === 'layer_1_rules') {
                    $layer1Correct++;
                }
            } else {
                $failures[] = [
                    'index' => $idx,
                    'query' => $query,
                    'expected' => $expected,
                    'actual' => $routing['intent'],
                    'layer' => $routing['layer'],
                ];
            }
        }

        $layer1CaptureRate = ($layer1Captured / $totalQueries) * 100;
        $overallAccuracy = (($totalQueries - count($failures)) / $totalQueries) * 100;

        if (!empty($failures)) {
            fwrite(STDERR, "\nParaphrase failures: " . json_encode($failures, JSON_PRETTY_PRINT) . "\n");
        }

        // Target: Lapisan 1 menangkap mayoritas (> 85%), total akurasi > 95%
        $this->assertGreaterThanOrEqual(85.0, $layer1CaptureRate, "Lapisan 1 wajib menangkap mayoritas kueri (aktual: {$layer1CaptureRate}%).");
        $this->assertGreaterThanOrEqual(95.0, $overallAccuracy, "Akurasi total wajib melebihi 95% (aktual: {$overallAccuracy}%).");
        $this->assertEmpty($failures, 'Semua kueri dalam set uji wajib berhasil diklasifikasi dengan benar.');
    }

    /**
     * 2. Pengujian Zero Provider Call untuk kueri yang tertangkap Lapisan 1.
     */
    public function test_layer_1_captured_queries_consume_zero_provider_calls(): void
    {
        Order::factory()->create([
            'order_number' => 'ORD-PARA-001',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => 'processing',
            'order_date' => now(),
        ]);

        $testQueries = [
            'pesanan saya sampai mana',
            'apakah cucian saya sudah dicuci',
            'berat timbangan cucian saya berapa kg',
            'status pembayaran pesanan ORD-PARA-001',
            'berapa kali saya memesan di laundryku',
            'total pengeluaran saya untuk laundry',
            'pesanan terakhir saya kapan',
            'daftar harga cuci kiloan',
            'jam buka toko laundry mitra',
            'nama toko laundry terdekat',
            'siapa saya',
            'kamu itu siapa',
        ];

        foreach ($testQueries as $query) {
            $initialCalls = count($this->client->calls);
            $res = $this->service->chat($this->customer, null, $query);

            $this->assertFalse($res['fallback']);
            $this->assertCount(
                $initialCalls,
                $this->client->calls,
                "Kueri '{$query}' tertangkap Lapisan 1 sehingga tidak boleh memanggil provider model (0 token)."
            );
        }
    }

    /**
     * 3. Pengujian Pertanyaan Lanjutan Pendek (Follow-Up) dalam Sesi yang Sama.
     */
    public function test_conversational_context_short_follow_ups(): void
    {
        $conv = AiConversation::create([
            'user_id' => $this->customer->id,
            'title' => 'Percakapan Konteks Lanjutan',
        ]);

        // Interaksi 1: Tanya pesanan aktif
        $res1 = $this->service->chat($this->customer, $conv->id, 'pesanan saya sampai mana');
        $this->assertFalse($res1['fallback']);

        // Interaksi 2: Follow-up pendek "kalau yang selesai?"
        $res2 = $this->service->chat($this->customer, $conv->id, 'kalau yang selesai?');
        $this->assertFalse($res2['fallback']);
        $this->assertStringContainsString('pesanan', mb_strtolower($res2['reply']));

        // Interaksi 3: Follow-up pendek "yang kemarin?"
        $res3 = $this->service->chat($this->customer, $conv->id, 'yang kemarin?');
        $this->assertFalse($res3['fallback']);

        // Interaksi 4: Follow-up pendek "terus bayarnya?"
        $res4 = $this->service->chat($this->customer, $conv->id, 'terus bayarnya?');
        $this->assertFalse($res4['fallback']);
    }

    /**
     * 4. Pengujian Pertanyaan Gabungan (Multi-Intent Compound Queries).
     */
    public function test_compound_multi_intent_queries(): void
    {
        Order::factory()->create([
            'order_number' => 'ORD-COMP-001',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => 'processing',
        ]);

        $res = $this->service->chat($this->customer, null, 'siapa kamu dan pesanan saya sampai mana');

        $this->assertFalse($res['fallback']);
        $this->assertStringContainsString('Saya LANDY', $res['reply']);
        $this->assertStringContainsString('ORD-COMP-001', $res['reply']);
        $this->assertCount(0, $this->client->calls, 'Pertanyaan gabungan tidak boleh memanggil provider model.');
    }

    /**
     * 5. Pengujian Isolasi Data dan Penolakan Keras Tindakan Tulis / Mutasi.
     */
    public function test_isolation_and_write_mutation_strict_refusal(): void
    {
        // 5a. Isolasi Data: User A tidak bisa mengintip pesanan User B
        $userB = User::factory()->create(['role' => 'customer']);
        $orderB = Order::factory()->create([
            'order_number' => 'ORD-SECRET-999',
            'customer_id' => $userB->id,
            'store_id' => $this->store->id,
            'status' => 'completed',
        ]);

        $resSneak = $this->service->chat($this->customer, null, "cek status pesanan {$orderB->order_number}");
        $this->assertStringNotContainsString('selesai', $resSneak['reply']);
        $this->assertStringContainsString('tidak ditemukan', $resSneak['reply']);

        // 5b. Penolakan Aksi Tulis / Mutasi (Zero Mutation)
        $ordersCountBefore = Order::count();
        $this->client->mockReply = 'Maaf, aksi pembatalan, pengubahan data, atau pengajuan refund tidak dapat dilakukan melalui chat. Silakan gunakan menu aplikasi atau hubungi customer service kami.';

        $writeQueries = [
            'batalkan pesanan saya sekarang',
            'ubah alamat pengantaran pesanan',
            'tolong refund uang saya',
            'ganti nomor telepon akun saya',
            'hapus data pesanan kemarin',
        ];

        foreach ($writeQueries as $wq) {
            $resWrite = $this->service->chat($this->customer, null, $wq);
            $lowerReply = mb_strtolower($resWrite['reply']);

            // Jawaban harus menolak aksi tulis atau mengarahkan ke menu aplikasi/CS
            $refusalConfirmed = str_contains($lowerReply, 'tidak dapat')
                || str_contains($lowerReply, 'hubungi customer service')
                || str_contains($lowerReply, 'tidak bisa')
                || str_contains($lowerReply, 'bukan aksi')
                || str_contains($lowerReply, 'menu')
                || str_contains($lowerReply, 'melalui');

            $this->assertTrue(
                $refusalConfirmed,
                "Aksi tulis/mutasi '{$wq}' wajib ditolak secara eksplisit. Balasan: {$resWrite['reply']}"
            );
        }

        // Verifikasi zero database mutation
        $this->assertEquals($ordersCountBefore, Order::count(), 'Aksi tulis tidak boleh memutasi atau menambah pesanan di database.');
    }

    /**
     * 6. Pengujian Audit Logging dan Sanitasi PII pada Unknown Queries.
     */
    public function test_audit_logging_and_pii_sanitization(): void
    {
        $res = $this->service->chat($this->customer, null, 'pesanan saya sampai mana');

        $latestLog = AiUsageLog::where('user_id', $this->customer->id)->latest('id')->first();
        $this->assertNotNull($latestLog);
        $this->assertEquals(0, $latestLog->prompt_tokens);
        $this->assertEquals(0, $latestLog->completion_tokens);
        $this->assertIsArray($latestLog->flags);
        $this->assertEquals('order_progress', $latestLog->flags['classified_intent'] ?? null);
        $this->assertEquals('layer_1_rules', $latestLog->flags['classification_layer'] ?? null);

        // Uji sanitasi PII pada teks normalizer
        $dirtyText = 'email saya budi.test@example.com dan nomor wa 081234567890 mau tanya laundry';
        $sanitized = $this->normalizer->sanitizePii($dirtyText);

        $this->assertStringNotContainsString('budi.test@example.com', $sanitized);
        $this->assertStringNotContainsString('081234567890', $sanitized);
        $this->assertStringContainsString('[EMAIL_MASKED]', $sanitized);
        $this->assertStringContainsString('[PHONE_MASKED]', $sanitized);
    }
}
