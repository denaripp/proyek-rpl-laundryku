<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealHttpChatbotIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $storeAdmin;
    protected User $courier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->customer = User::factory()->create([
            'name' => 'Budi Pelanggan',
            'email' => 'budi@laundryku.test',
            'role' => 'customer',
        ]);
        $this->assignRole($this->customer, 'customer');

        $this->storeAdmin = User::factory()->create([
            'name' => 'Hendra Toko',
            'email' => 'hendra@laundryku.test',
            'role' => 'store_admin',
        ]);
        $this->assignRole($this->storeAdmin, 'store_owner');

        $store = Store::factory()->create([
            'owner_user_id' => $this->storeAdmin->id,
            'name' => 'Berkah Laundry',
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->courier = User::factory()->create([
            'name' => 'Ahmad Kurir',
            'email' => 'ahmad@laundryku.test',
            'role' => 'courier',
        ]);
        $this->assignRole($this->courier, 'courier');

        Courier::create([
            'user_id' => $this->courier->id,
            'store_id' => $store->id,
            'vehicle_type' => 'Motor Beat',
            'plate_number' => 'B 1234 ABC',
            'is_available' => true,
            'status' => 'approved',
        ]);
    }

    protected function assignRole(User $user, string $roleName): void
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['guard_name' => 'web']);
        UserRole::firstOrCreate(['user_id' => $user->id, 'role_id' => $role->id]);
    }

    /**
     * Test Guest asking via real HTTP endpoint /api/v1/chatbot/guest/ask
     */
    public function test_guest_http_endpoint_answers_all_bot_identity_variations(): void
    {
        $variations = [
            'siapa anda',
            'siapa kamu',
            'kamu siapa',
            'lu siapa',
            'siapa anda/kamu',
            'siapa lu',
        ];

        foreach ($variations as $q) {
            $response = $this->postJson('/api/v1/chatbot/guest/ask', [
                'message' => $q,
            ]);

            $response->assertStatus(200);
            $answer = $response->json('data.answer');

            $this->assertNotEmpty($answer, "Jawaban untuk guest '{$q}' tidak boleh kosong.");
            $this->assertStringContainsString('Saya LANDY, asisten AI LaundryKu', $answer, "Guest '{$q}' harus dijawab dengan identitas LANDY.");
            $this->assertStringNotContainsString('Mau tanya soal itu?', $answer);
            $this->assertStringNotContainsString('cs@landuryku.id', $answer);
        }
    }

    /**
     * Test Customer asking via real HTTP endpoints /api/v1/chatbot/sessions and messages
     */
    public function test_customer_http_endpoint_answers_all_bot_identity_variations(): void
    {
        Sanctum::actingAs($this->customer, ['role:customer']);

        $sessionRes = $this->postJson('/api/v1/chatbot/sessions', [
            'title' => 'Sesi Percakapan Customer',
        ]);
        $sessionRes->assertStatus(201);
        $sessionId = $sessionRes->json('data.id');

        $variations = [
            'siapa anda',
            'siapa kamu',
            'kamu siapa',
            'lu siapa',
            'siapa anda/kamu',
        ];

        foreach ($variations as $q) {
            $msgRes = $this->postJson("/api/v1/chatbot/sessions/{$sessionId}/messages", [
                'message' => $q,
            ]);

            $msgRes->assertStatus(200);
            $answer = $msgRes->json('data.assistant_message.content');

            $this->assertNotEmpty($answer, "Jawaban untuk customer '{$q}' tidak boleh kosong.");
            $this->assertStringContainsString('Saya LANDY, asisten AI LaundryKu', $answer);
            $this->assertStringNotContainsString('Mau tanya soal itu?', $answer);
            $this->assertStringNotContainsString('cs@landuryku.id', $answer);
        }
    }

    /**
     * Test Store Admin asking via real HTTP endpoint
     */
    public function test_store_admin_http_endpoint_answers_all_bot_identity_variations(): void
    {
        Sanctum::actingAs($this->storeAdmin, ['role:store_admin']);

        $sessionRes = $this->postJson('/api/v1/chatbot/sessions', [
            'title' => 'Sesi Percakapan Toko',
        ]);
        $sessionRes->assertStatus(201);
        $sessionId = $sessionRes->json('data.id');

        $variations = [
            'siapa anda',
            'siapa kamu',
            'kamu siapa',
            'lu siapa',
            'siapa anda/kamu',
        ];

        foreach ($variations as $q) {
            $msgRes = $this->postJson("/api/v1/chatbot/sessions/{$sessionId}/messages", [
                'message' => $q,
            ]);

            $msgRes->assertStatus(200);
            $answer = $msgRes->json('data.assistant_message.content');

            $this->assertNotEmpty($answer, "Jawaban untuk admin toko '{$q}' tidak boleh kosong.");
            $this->assertStringContainsString('Saya LANDY, asisten AI LaundryKu', $answer);
            $this->assertStringNotContainsString('Mau tanya soal itu?', $answer);
            $this->assertStringNotContainsString('cs@landuryku.id', $answer);
        }
    }

    /**
     * Test Courier asking via real HTTP endpoint
     */
    public function test_courier_http_endpoint_answers_all_bot_identity_variations(): void
    {
        Sanctum::actingAs($this->courier, ['role:courier']);

        $sessionRes = $this->postJson('/api/v1/chatbot/sessions', [
            'title' => 'Sesi Percakapan Kurir',
        ]);
        $sessionRes->assertStatus(201);
        $sessionId = $sessionRes->json('data.id');

        $variations = [
            'siapa anda',
            'siapa kamu',
            'kamu siapa',
            'lu siapa',
            'siapa anda/kamu',
        ];

        foreach ($variations as $q) {
            $msgRes = $this->postJson("/api/v1/chatbot/sessions/{$sessionId}/messages", [
                'message' => $q,
            ]);

            $msgRes->assertStatus(200);
            $answer = $msgRes->json('data.assistant_message.content');

            $this->assertNotEmpty($answer, "Jawaban untuk kurir '{$q}' tidak boleh kosong.");
            $this->assertStringContainsString('Saya LANDY, asisten AI LaundryKu', $answer);
            $this->assertStringNotContainsString('Mau tanya soal itu?', $answer);
            $this->assertStringNotContainsString('cs@landuryku.id', $answer);
        }
    }

    /**
     * Test regression: dua user dengan token berbeda, "siapa saya" harus menjawab nama pemilik token.
     */
    public function test_two_users_with_different_tokens_answer_with_token_owners_name(): void
    {
        $userA = User::factory()->create([
            'name' => 'User Alpha Token',
            'role' => 'customer',
        ]);
        $this->assignRole($userA, 'customer');

        $userB = User::factory()->create([
            'name' => 'User Beta Token',
            'role' => 'customer',
        ]);
        $this->assignRole($userB, 'customer');

        $tokenA = $userA->createToken('token_a')->plainTextToken;
        $tokenB = $userB->createToken('token_b')->plainTextToken;

        // User A bertanya "siapa saya" menggunakan Token A
        $resA = $this->withToken($tokenA)->postJson('/api/v1/chatbot/sessions', [
            'message' => 'siapa saya',
        ]);
        $resA->assertStatus(201);
        $answerA = $resA->json('data.latest_message.content');
        $this->assertStringContainsString('User Alpha Token', $answerA);
        $this->assertStringNotContainsString('User Beta Token', $answerA);

        // User B bertanya "siapa saya" menggunakan Token B
        auth()->forgetGuards();
        $resB = $this->withToken($tokenB)->postJson('/api/v1/chatbot/sessions', [
            'message' => 'siapa saya',
        ]);
        $resB->assertStatus(201);
        $answerB = $resB->json('data.latest_message.content');
        $this->assertStringContainsString('User Beta Token', $answerB);
        $this->assertStringNotContainsString('User Alpha Token', $answerB);

        // Kirim pesan lanjutan pada sesi masing-masing
        $sessionIdA = $resA->json('data.id');
        $sessionIdB = $resB->json('data.id');

        auth()->forgetGuards();
        $msgA = $this->withToken($tokenA)->postJson("/api/v1/chatbot/sessions/{$sessionIdA}/messages", [
            'message' => 'siapa saya',
        ]);
        $msgA->assertStatus(200);
        $this->assertStringContainsString('User Alpha Token', $msgA->json('data.assistant_message.content'));
        $this->assertStringNotContainsString('User Beta Token', $msgA->json('data.assistant_message.content'));

        auth()->forgetGuards();
        $msgB = $this->withToken($tokenB)->postJson("/api/v1/chatbot/sessions/{$sessionIdB}/messages", [
            'message' => 'siapa saya',
        ]);
        $msgB->assertStatus(200);
        $this->assertStringContainsString('User Beta Token', $msgB->json('data.assistant_message.content'));
        $this->assertStringNotContainsString('User Alpha Token', $msgB->json('data.assistant_message.content'));
    }
}
