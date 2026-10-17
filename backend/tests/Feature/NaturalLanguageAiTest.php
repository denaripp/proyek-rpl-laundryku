<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AiChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NaturalLanguageAiTest extends TestCase
{
    use RefreshDatabase;

    protected AiChatService $service;

    protected function setUp(): void
    {
        parent::setUp();
        app()['env'] = 'local';
        config([
            'ai.mock_in_local' => true,
        ]);
        $this->service = new AiChatService();
    }

    public function test_it_handles_all_16_natural_language_and_knowledge_scenarios(): void
    {
        $scenarios = [
            [
                'id' => 1,
                'query' => 'Mau batalin pesanan.',
                'expected' => ['batal', '/orders'],
            ],
            [
                'id' => 2,
                'query' => 'Kurir gue belum dateng.',
                'expected' => ['kurir', '/orders', '/chat'],
            ],
            [
                'id' => 3,
                'query' => 'Gue mau komplain.',
                'expected' => ['komplain', '1x24', '/orders'],
            ],
            [
                'id' => 4,
                'query' => 'Cara hubungi CS?',
                'expected' => ['cs', '/chat'],
            ],
            [
                'id' => 5,
                'query' => 'Laundry gue udah lama banget.',
                'expected' => ['reguler', 'express', '/orders'],
            ],
            [
                'id' => 6,
                'query' => 'Uang gue balik ga?',
                'expected' => ['refund', 'xendit'],
            ],
            [
                'id' => 7,
                'query' => 'Pembayaran gue gagal.',
                'expected' => ['bayar', '/orders'],
            ],
            [
                'id' => 8,
                'query' => 'Baju gue rusak setelah dicuci.',
                'expected' => ['foto', 'komplain', '1x24'],
            ],
            [
                'id' => 9,
                'query' => 'Gimana cara daftar mitra?',
                'expected' => ['mitra', 'toko', '/upgrade'],
            ],
            [
                'id' => 10,
                'query' => 'Mau jadi kurir.',
                'expected' => ['kurir', 'sim c', '/upgrade'],
            ],
            [
                'id' => 11,
                'query' => 'Alamat pesanan salah.',
                'expected' => ['alamat', '/chat'],
            ],
            [
                'id' => 12,
                'query' => 'Pesanan gue belum berubah status.',
                'expected' => ['status', '/chat'],
            ],
            [
                'id' => 13,
                'query' => 'Gimana cara daftar?',
                'expected' => ['daftar', 'otp', '/auth'],
            ],
            [
                'id' => 14,
                'query' => 'Terus syaratnya apa?',
                'history' => [
                    ['role' => 'user', 'content' => 'Mau jadi kurir.'],
                    ['role' => 'assistant', 'content' => 'Silakan daftar kurir di /upgrade.'],
                ],
                'expected' => ['sim c', 'ktp', '/upgrade'],
            ],
            [
                'id' => 15,
                'query' => 'Kurir blm dateng nih, laundryan gue blom dijemput',
                'expected' => ['kurir', '/orders'],
            ],
            [
                'id' => 16,
                'query' => 'Toko Laundry Berkah buka pas Idul Fitri ga?',
                'expected' => ['toko', 'jam operasional', '/stores'],
            ],
        ];

        foreach ($scenarios as $s) {
            $history = $s['history'] ?? [];
            $response = $this->service->generateFallbackResponse($s['query'], null, $history);
            $lower = strtolower($response);

            // Must not stonewall with generic clarification
            $this->assertStringNotContainsString(
                'bisa dijelaskan lebih spesifik',
                $lower,
                "Scenario {$s['id']} should not ask for clarification unnecessarily."
            );

            // Must contain key procedural terms
            foreach ($s['expected'] as $term) {
                $this->assertStringContainsString(
                    strtolower($term),
                    $lower,
                    "Scenario {$s['id']} ({$s['query']}) response missing term: {$term}"
                );
        }
    }
}

    public function test_it_handles_identity_logout_partner_and_no_minutes_for_courier_eta(): void
    {
        // 1. "kamu siapa"
        $respIdentity = $this->service->generateFallbackResponse('kamu siapa');
        $this->assertStringContainsString('Asisten LaundryKu', $respIdentity);
        $this->assertStringNotContainsString('tergantung pada outlet toko laundry', strtolower($respIdentity));

        // 2. "bisa bantu apa"
        $respHelp = $this->service->generateFallbackResponse('bisa bantu apa');
        $this->assertStringContainsString('Asisten LaundryKu', $respHelp);
        $this->assertStringContainsString('/orders', $respHelp);
        $this->assertStringContainsString('/stores', $respHelp);

        // 3. "cara logout"
        $respLogout = $this->service->generateFallbackResponse('cara logout');
        $this->assertStringContainsString('/profile', $respLogout);
        $this->assertStringContainsString('logout', strtolower($respLogout));

        // 4. "cara daftar mitra"
        $respMitra = $this->service->generateFallbackResponse('cara daftar mitra');
        $this->assertStringContainsString('/upgrade', $respMitra);
        $this->assertStringContainsString('Pendaftaran Toko', $respMitra);

        // 5. "Kurir sampai jam berapa" (must NOT produce minutes)
        $respCourierEta = $this->service->generateFallbackResponse('Kurir sampai jam berapa');
        $this->assertStringNotContainsString('menit', strtolower($respCourierEta));
        $this->assertStringContainsString('kurir', strtolower($respCourierEta));
        $this->assertStringContainsString('/orders', $respCourierEta);
    }

    public function test_it_handles_role_specific_user_context_and_guest_privacy(): void
    {
        // 1. Guest asking personal questions must be prompted to login
        $guestWho = $this->service->generateFallbackResponse('siapa saya');
        $this->assertStringContainsString('/auth', $guestWho);
        $this->assertStringContainsString('belum', strtolower($guestWho));

        $guestStore = $this->service->generateFallbackResponse('saya punya toko apa');
        $this->assertStringContainsString('/auth', $guestStore);
        $this->assertStringContainsString('belum login', strtolower($guestStore));

        $guestTask = $this->service->generateFallbackResponse('tugas saya apa');
        $this->assertStringContainsString('/auth', $guestTask);
        $this->assertStringContainsString('belum login', strtolower($guestTask));

        // 2. Customer login: "siapa saya" answers name, role customer, and active mode
        $customerRole = \App\Models\Role::firstOrCreate(['code' => 'customer'], ['name' => 'Customer', 'level' => 10]);
        $customer = \App\Models\User::factory()->create([
            'name' => 'Siti Pelanggan',
            'active_mode' => 'customer',
        ]);
        \App\Models\UserRole::create([
            'user_id' => $customer->id,
            'role_id' => $customerRole->id,
            'status' => 'active',
        ]);

        $custResp = $this->service->generateFallbackResponse('siapa saya', $customer);
        $this->assertStringContainsString('Siti Pelanggan', $custResp);
        $this->assertStringContainsString('customer', strtolower($custResp));

        // 3. Store Admin login: "saya punya toko apa" answers store name
        $storeAdminRole = \App\Models\Role::firstOrCreate(['code' => 'store_admin'], ['name' => 'Admin Toko', 'level' => 50]);
        $storeOwner = \App\Models\User::factory()->create([
            'name' => 'Bambang Mitra Toko',
            'active_mode' => 'store',
        ]);
        \App\Models\UserRole::create([
            'user_id' => $storeOwner->id,
            'role_id' => $storeAdminRole->id,
            'status' => 'active',
        ]);
        $store = \App\Models\Store::create([
            'owner_user_id' => $storeOwner->id,
            'name' => 'Laundry Berkah Barokah',
            'address_text' => 'Jl. Pahlawan No. 10',
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        $storeResp = $this->service->generateFallbackResponse('saya punya toko apa', $storeOwner);
        $this->assertStringContainsString('Laundry Berkah Barokah', $storeResp);
        $this->assertStringContainsString('Bambang Mitra Toko', $storeResp);

        // 4. Courier login: "tugas saya apa" answers courier assignment
        $courierRole = \App\Models\Role::firstOrCreate(['code' => 'courier'], ['name' => 'Kurir', 'level' => 30]);
        $courierUser = \App\Models\User::factory()->create([
            'name' => 'Rian Kurir Sigap',
            'active_mode' => 'courier',
        ]);
        \App\Models\UserRole::create([
            'user_id' => $courierUser->id,
            'role_id' => $courierRole->id,
            'status' => 'active',
        ]);
        $courierProfile = \App\Models\CourierProfile::create([
            'user_id' => $courierUser->id,
            'nik' => '3201123456780001',
            'sim_number' => '1234-5678-9012',
            'vehicle_plate' => 'B 1234 XYZ',
            'vehicle_type' => 'motorcycle',
            'verification_status' => 'verified',
        ]);
        \App\Models\CourierStoreAssignment::create([
            'courier_id' => $courierProfile->id,
            'store_id' => $store->id,
            'recruited_by' => $storeOwner->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $courierResp = $this->service->generateFallbackResponse('tugas saya apa', $courierUser);
        $this->assertStringContainsString('Laundry Berkah Barokah', $courierResp);
        $this->assertStringContainsString('Rian Kurir Sigap', $courierResp);
    }

    public function test_chatbot_endpoints_with_sanctum_and_guest(): void
    {
        // 1. Guest endpoint without auth asking "siapa saya" -> asks to login
        $guestResponse = $this->postJson('/api/v1/chatbot/guest/ask', [
            'message' => 'siapa saya',
        ]);
        $guestResponse->assertStatus(200);
        $this->assertStringContainsString('/auth', $guestResponse->json('data.answer'));

        // 2. Customer logged in via Sanctum asking "siapa saya" via session endpoint
        $customerRole = \App\Models\Role::firstOrCreate(['code' => 'customer'], ['name' => 'Customer', 'level' => 10]);
        $customer = \App\Models\User::factory()->create([
            'name' => 'Dewi Lestari',
            'active_mode' => 'customer',
        ]);
        \App\Models\UserRole::create([
            'user_id' => $customer->id,
            'role_id' => $customerRole->id,
            'status' => 'active',
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($customer);

        $sessionResponse = $this->postJson('/api/v1/chatbot/sessions', [
            'message' => 'siapa saya',
        ]);
        $sessionResponse->assertStatus(201);
        $assistantMsg = collect($sessionResponse->json('data.messages'))->firstWhere('role', 'assistant');
        $this->assertNotNull($assistantMsg);
        $this->assertStringContainsString('Dewi Lestari', $assistantMsg['content']);
        $this->assertStringContainsString('customer', strtolower($assistantMsg['content']));
    }

    public function test_it_handles_off_topic_queries_and_valid_laundry_questions_correctly(): void
    {
        // 1. "buatkan coding python untuk menuliskan nama anda" -> fallback off-topic
        $res1 = $this->postJson('/api/v1/chatbot/guest/ask', [
            'message' => 'buatkan coding python untuk menuliskan nama anda',
        ]);
        $res1->assertStatus(200);
        $ans1 = $res1->json('data.answer');
        $this->assertStringContainsString('Maaf, aku cuma bisa bantu soal layanan LaundryKu', $ans1);
        $this->assertStringContainsString('Mau tanya soal itu?', $ans1);
        $this->assertStringNotContainsString('Metode Pembayaran Resmi di LaundryKu', $ans1);

        // 2. "siapa presiden indonesia" -> fallback off-topic
        $res2 = $this->postJson('/api/v1/chatbot/guest/ask', [
            'message' => 'siapa presiden indonesia',
        ]);
        $res2->assertStatus(200);
        $ans2 = $res2->json('data.answer');
        $this->assertStringContainsString('Maaf, aku cuma bisa bantu soal layanan LaundryKu', $ans2);
        $this->assertStringContainsString('Mau tanya soal itu?', $ans2);

        // 3. "metode pembayaran apa saja?" -> daftar pembayaran (tetap jalan)
        $res3 = $this->postJson('/api/v1/chatbot/guest/ask', [
            'message' => 'metode pembayaran apa saja?',
        ]);
        $res3->assertStatus(200);
        $ans3 = $res3->json('data.answer');
        $this->assertStringContainsString('Metode Pembayaran Resmi di LaundryKu', $ans3);
        $this->assertStringContainsString('QRIS', $ans3);
        $this->assertStringContainsString('COD', $ans3);

        // 4. "berapa harga cuci kiloan?" -> jawaban harga (tetap jalan)
        $res4 = $this->postJson('/api/v1/chatbot/guest/ask', [
            'message' => 'berapa harga cuci kiloan?',
        ]);
        $res4->assertStatus(200);
        $ans4 = $res4->json('data.answer');
        $this->assertStringContainsString('Informasi Harga & Tarif Layanan', $ans4);
        $this->assertStringContainsString('Cuci Kiloan', $ans4);

        // 5. "cara melacak pesanan" -> jawaban tracking (tetap jalan)
        $res5 = $this->postJson('/api/v1/chatbot/guest/ask', [
            'message' => 'cara melacak pesanan',
        ]);
        $res5->assertStatus(200);
        $ans5 = $res5->json('data.answer');
        $this->assertStringContainsString('Cara Melacak Status Pesanan', $ans5);
        $this->assertStringContainsString('/orders', $ans5);
    }
}


