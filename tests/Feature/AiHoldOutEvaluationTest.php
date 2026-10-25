<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Store;
use App\Models\User;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiClientInterface;
use App\Services\Ai\AiProviderException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MockHoldoutClient implements AiClientInterface
{
    public array $calls = [];

    public function chat(array $messages, array $tools = []): array
    {
        $this->calls[] = ['messages' => $messages, 'tools' => $tools];
        return [
            'content' => 'Simulasi model fallback berbasis konteks panduan.',
            'tool_calls' => [],
            'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 10],
        ];
    }
}

class AiHoldOutEvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $courierUser;
    protected User $storeAdmin;
    protected User $superAdmin;
    protected Store $store;
    protected MockHoldoutClient $mockClient;
    protected AiAssistantService $assistant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = new MockHoldoutClient();
        $this->app->instance(AiClientInterface::class, $this->mockClient);
        config(['ai.rate_limit_per_hour' => 2000]);
        \App\Models\SystemConfiguration::updateOrCreate(['key' => 'AI_RATE_LIMIT_PER_HOUR'], ['value' => ['value' => 2000]]);
        \App\Models\SystemConfiguration::updateOrCreate(['key' => 'AI_ABUSE_THRESHOLD'], ['value' => ['value' => 2000]]);

        // 1. Customer
        $this->customer = User::factory()->create([
            'name' => 'Budi Evaluasi',
            'role' => 'customer',
            'email' => 'customer_eval@laundryku.test',
            'phone' => '081234567801',
            'is_active' => true,
        ]);

        // 2. Courier
        $this->courierUser = User::factory()->create([
            'name' => 'Agus Kurir Evaluasi',
            'role' => 'courier',
            'email' => 'courier_eval@laundryku.test',
            'phone' => '081234567802',
            'is_active' => true,
        ]);
        $courier = Courier::create([
            'user_id' => $this->courierUser->id,
            'vehicle_type' => 'motor',
            'vehicle_plate' => 'B 1234 XYZ',
            'is_active' => true,
            'is_available' => true,
        ]);

        // 3. Store Admin
        $this->storeAdmin = User::factory()->create([
            'name' => 'Siti Mitra Evaluasi',
            'role' => 'store_admin',
            'email' => 'mitra_eval@laundryku.test',
            'phone' => '081234567803',
            'is_active' => true,
        ]);
        $this->store = Store::factory()->create([
            'owner_user_id' => $this->storeAdmin->id,
            'name' => 'Berkah Laundry Evaluasi',
            'slug' => 'berkah-laundry-evaluasi',
            'status' => 'approved',
            'is_active' => true,
            'average_rating' => 4.9,
            'review_count' => 15,
            'operational_hours' => 'Senin - Sabtu: 08:00 - 20:00 WIB',
            'cod_enabled' => true,
            'address' => 'Jl. Evaluasi No. 1, Kota Bandung',
            'latitude' => -6.917464,
            'longitude' => 107.619123,
        ]);

        // 4. Super Admin
        $this->superAdmin = User::factory()->create([
            'name' => 'Admin Evaluasi',
            'role' => 'super_admin',
            'email' => 'admin_eval@laundryku.test',
            'phone' => '081234567804',
            'is_active' => true,
        ]);

        // Customer sample orders
        $order1 = Order::create([
            'order_number' => 'ORD-EVAL-001',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'qris',
            'total_amount' => 28000,
            'total_price' => 28000,
            'actual_weight' => 3.5,
            'weight' => 3.5,
            'weighed_at' => now(),
            'order_date' => now()->subDay(),
        ]);

        CourierTask::create([
            'order_id' => $order1->id,
            'courier_id' => $courier->id,
            'task_type' => 'pickup',
            'status' => 'picked_up',
        ]);

        $this->assistant = $this->app->make(AiAssistantService::class);
    }

    public function test_holdout_dataset_achieves_above_95_percent_accuracy(): void
    {
        $datasetPath = storage_path('holdout_dataset.php');
        $this->assertFileExists($datasetPath);

        $dataset = require $datasetPath;
        $this->assertGreaterThanOrEqual(150, count($dataset));

        $usersByRole = [
            'customer' => $this->customer,
            'courier' => $this->courierUser,
            'store_admin' => $this->storeAdmin,
            'super_admin' => $this->superAdmin,
        ];

        $passed = 0;
        $total = count($dataset);
        $failures = [];

        foreach ($dataset as $idx => $item) {
            $user = $usersByRole[$item['role']] ?? $this->customer;
            $cat = $item['category'];
            $query = $item['query'];
            $expectedKeywords = $item['expected_keywords'] ?? [];

            $res = $this->assistant->chat($user, null, $query);
            $reply = $res['reply'] ?? '';

            $isOk = true;
            if ($cat === 'out_of_scope') {
                if (!str_contains($reply, 'di luar layanan LaundryKu') && !str_contains($reply, 'melanggar') && !str_contains($reply, 'tidak dapat diproses')) {
                    $isOk = false;
                }
            } elseif ($cat === 'panduan') {
                $hasKw = false;
                foreach ($expectedKeywords as $kw) {
                    if (stripos($reply, $kw) !== false) {
                        $hasKw = true;
                        break;
                    }
                }
                if (!$hasKw || str_contains($reply, 'Simulasi model fallback')) {
                    $isOk = false;
                }
            } elseif ($cat === 'data') {
                $hasKw = false;
                foreach ($expectedKeywords as $kw) {
                    if (stripos($reply, $kw) !== false) {
                        $hasKw = true;
                        break;
                    }
                }
                if (!$hasKw || str_contains($reply, 'Simulasi model fallback')) {
                    $isOk = false;
                }
            } elseif ($cat === 'gaul_typo') {
                $hasKw = false;
                foreach ($expectedKeywords as $kw) {
                    if (stripos($reply, $kw) !== false) {
                        $hasKw = true;
                        break;
                    }
                }
                if (!$hasKw || str_contains($reply, 'Simulasi model fallback')) {
                    $isOk = false;
                }
            } elseif ($cat === 'ganda') {
                $matchedKw = 0;
                foreach ($expectedKeywords as $kw) {
                    if (stripos($reply, $kw) !== false) {
                        $matchedKw++;
                    }
                }
                if ($matchedKw < 2 || str_contains($reply, 'Simulasi model fallback')) {
                    $isOk = false;
                }
            }

            if ($isOk) {
                $passed++;
            } else {
                $failures[] = [
                    'index' => $idx + 1,
                    'category' => $cat,
                    'query' => $query,
                    'reply' => substr($reply, 0, 100),
                ];
            }
        }

        $accuracy = round(($passed / $total) * 100, 2);
        $this->assertGreaterThanOrEqual(95.0, $accuracy, 'Hold-out accuracy must be >= 95%. Failures: ' . json_encode($failures));
    }

    public function test_mutation_via_chat_is_politely_rejected_with_guide(): void
    {
        $res = $this->assistant->chat($this->customer, null, 'Tolong batalkan pesanan ORD-EVAL-001 saya sekarang!');
        $this->assertStringContainsString('tidak dapat membatalkan pesanan secara langsung', $res['reply']);
        $this->assertStringContainsString('Panduan Cara Membatalkan Pesanan', $res['reply']);
    }

    public function test_procedural_guide_consumes_zero_tokens_without_model(): void
    {
        $callsBefore = count($this->mockClient->calls);
        $res = $this->assistant->chat($this->customer, null, 'Bagaimana cara pesan laundry di aplikasi?');
        $callsAfter = count($this->mockClient->calls);

        $this->assertEquals($callsBefore, $callsAfter, 'Procedural guide should not call model (0 tokens).');
        $this->assertStringContainsString('Panduan Cara Pesan', $res['reply']);
        $this->assertStringContainsString('Cari Toko', $res['reply']);
    }

    public function test_greetings_and_thanks_answered_deterministically(): void
    {
        $callsBefore = count($this->mockClient->calls);
        $resGreet = $this->assistant->chat($this->customer, null, 'Halo Landy');
        $resThanks = $this->assistant->chat($this->customer, null, 'Terima kasih banyak min');
        $callsAfter = count($this->mockClient->calls);

        $this->assertEquals($callsBefore, $callsAfter, 'Greetings and thanks should be deterministic (0 tokens).');
        $this->assertStringContainsString('Saya LANDY', $resGreet['reply']);
        $this->assertStringContainsString('Sama-sama', $resThanks['reply']);
    }

    public function test_mitra_cannot_access_other_store_data(): void
    {
        $otherStore = Store::factory()->create([
            'owner_user_id' => $this->customer->id,
            'name' => 'Toko Milik Orang Lain',
            'slug' => 'toko-milik-orang-lain',
            'status' => 'approved',
            'is_active' => true,
        ]);

        $res = $this->assistant->chat($this->storeAdmin, null, 'Tampilkan daftar harga di toko saya');
        $this->assertStringContainsString('Berkah Laundry Evaluasi', $res['reply']);
        $this->assertStringNotContainsString('Toko Milik Orang Lain', $res['reply']);
    }
}
