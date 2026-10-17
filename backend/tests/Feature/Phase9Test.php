<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase9Test extends TestCase
{
    use RefreshDatabase;

    protected User $customerA;
    protected User $customerB;
    protected User $storeOwnerA;
    protected Store $storeA;
    protected User $storeOwnerB;
    protected Store $storeB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'level' => 100]);
        Role::firstOrCreate(['code' => 'courier'], ['name' => 'Kurir', 'level' => 30]);
        Role::firstOrCreate(['code' => 'store_admin'], ['name' => 'Admin Toko', 'level' => 50]);
        Role::firstOrCreate(['code' => 'customer'], ['name' => 'Customer', 'level' => 10]);

        // 1. Customer A
        $this->customerA = User::factory()->create([
            'name' => 'Dewi Customer A',
            'phone' => '081211111111',
            'status' => 'active',
        ]);
        UserRole::create([
            'user_id' => $this->customerA->id,
            'role_id' => Role::where('code', 'customer')->first()->id,
            'status' => 'active',
        ]);

        // 2. Customer B (Unrelated third party)
        $this->customerB = User::factory()->create([
            'name' => 'Budi Customer B',
            'phone' => '081222222222',
            'status' => 'active',
        ]);
        UserRole::create([
            'user_id' => $this->customerB->id,
            'role_id' => Role::where('code', 'customer')->first()->id,
            'status' => 'active',
        ]);

        // 3. Store A & Owner A
        $this->storeOwnerA = User::factory()->create([
            'name' => 'Owner Toko A',
            'phone' => '081233333333',
            'status' => 'active',
        ]);
        UserRole::create([
            'user_id' => $this->storeOwnerA->id,
            'role_id' => Role::where('code', 'store_admin')->first()->id,
            'status' => 'active',
        ]);
        $this->storeA = Store::create([
            'owner_user_id' => $this->storeOwnerA->id,
            'name' => 'Laundry Bersih Jaya',
            'address_text' => 'Jl. Merdeka No. 10',
            'latitude' => -6.8900,
            'longitude' => 107.6100,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        // 4. Store B & Owner B (Competitor store)
        $this->storeOwnerB = User::factory()->create([
            'name' => 'Owner Toko B',
            'phone' => '081244444444',
            'status' => 'active',
        ]);
        UserRole::create([
            'user_id' => $this->storeOwnerB->id,
            'role_id' => Role::where('code', 'store_admin')->first()->id,
            'status' => 'active',
        ]);
        $this->storeB = Store::create([
            'owner_user_id' => $this->storeOwnerB->id,
            'name' => 'Laundry Kilat Express',
            'address_text' => 'Jl. Riau No. 20',
            'latitude' => -6.8950,
            'longitude' => 107.6150,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);
    }

    /**
     * Test 1: Customer A starts conversation and sends text message to Store A.
     */
    public function test_customer_can_start_conversation_and_send_text_message(): void
    {
        // 1. Start or Get Conversation
        $convResponse = $this->actingAs($this->customerA)
            ->postJson('/api/v1/conversations', [
                'store_id' => $this->storeA->id,
            ]);

        $convResponse->assertStatus(200)
            ->assertJsonPath('data.store_id', $this->storeA->id)
            ->assertJsonPath('data.customer_id', $this->customerA->id);

        $convId = $convResponse->json('data.id');

        // 2. Send Text Message
        $msgResponse = $this->actingAs($this->customerA)
            ->postJson("/api/v1/conversations/{$convId}/messages", [
                'body' => 'Halo admin, apakah bisa request deterjen khusus bayi?',
            ]);

        $msgResponse->assertStatus(201)
            ->assertJsonPath('data.body', 'Halo admin, apakah bisa request deterjen khusus bayi?')
            ->assertJsonPath('data.message_type', 'text')
            ->assertJsonPath('data.sender_id', $this->customerA->id);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $convId,
            'sender_id' => $this->customerA->id,
            'body' => 'Halo admin, apakah bisa request deterjen khusus bayi?',
        ]);

        // 3. Verify notification created for Store Owner A
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->storeOwnerA->id,
            'event_type' => 'CHAT_MESSAGE_RECEIVED',
        ]);
    }

    /**
     * Test 2: Store Owner A replies with text to Customer A.
     */
    public function test_store_admin_can_view_and_reply_to_customer_conversation(): void
    {
        $conversation = Conversation::create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customerA->id,
            'status' => 'active',
            'last_message_at' => now(),
        ]);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->customerA->id,
            'message_type' => 'text',
            'body' => 'Bisa jemput jam 9 pagi?',
        ]);

        // Store Owner views conversation
        $viewResponse = $this->actingAs($this->storeOwnerA)
            ->getJson("/api/v1/conversations/{$conversation->id}");

        $viewResponse->assertStatus(200)
            ->assertJsonCount(1, 'data.messages');

        // Store Owner replies
        $replyResponse = $this->actingAs($this->storeOwnerA)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => 'Bisa kak, kurir kami akan tiba pukul 09.00.',
            ]);

        $replyResponse->assertStatus(201)
            ->assertJsonPath('data.body', 'Bisa kak, kurir kami akan tiba pukul 09.00.')
            ->assertJsonPath('data.sender_id', $this->storeOwnerA->id);
    }

    /**
     * Test 3: Photo upload and secure private streaming.
     */
    public function test_customer_can_upload_photo_and_store_can_stream_it(): void
    {
        $conversation = Conversation::create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customerA->id,
            'status' => 'active',
        ]);

        $fakePhoto = UploadedFile::fake()->image('baju_noda.jpg', 600, 600);

        // Customer uploads photo
        $response = $this->actingAs($this->customerA)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => 'Tolong noda di bagian kerah ini dibersihkan ya.',
                'image' => $fakePhoto,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.message_type', 'image');

        $messageId = $response->json('data.id');
        $attachmentKey = $response->json('data.attachment_key');

        $this->assertNotNull($attachmentKey);
        Storage::disk('local')->assertExists($attachmentKey);

        // Store Admin streams photo
        $streamResponse = $this->actingAs($this->storeOwnerA)
            ->get("/api/v1/conversations/{$conversation->id}/attachments/{$messageId}");

        $streamResponse->assertStatus(200);
    }

    /**
     * Test 4: Access Isolation / Security Boundary (FR-CHAT-06).
     * Unauthorized Customer B or Store B cannot access or send messages in Customer A's conversation.
     */
    public function test_access_isolation_prevents_unauthorized_users_from_viewing_or_posting(): void
    {
        $conversation = Conversation::create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customerA->id,
            'status' => 'active',
        ]);

        $fakePhoto = UploadedFile::fake()->image('rahasia.jpg');
        $msg = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->customerA->id,
            'message_type' => 'image',
            'body' => 'Pesan rahasia',
            'attachment_key' => $fakePhoto->store("chat_attachments/{$conversation->id}", 'local'),
        ]);

        // 1. Customer B tries to view Conversation A -> 403 Forbidden
        $this->actingAs($this->customerB)
            ->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertStatus(403);

        // 2. Customer B tries to send message to Conversation A -> 403 Forbidden
        $this->actingAs($this->customerB)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => 'Mencoba menyusup chat orang lain',
            ])
            ->assertStatus(403);

        // 3. Customer B tries to stream attachment of Conversation A -> 403 Forbidden
        $this->actingAs($this->customerB)
            ->get("/api/v1/conversations/{$conversation->id}/attachments/{$msg->id}")
            ->assertStatus(403);

        // 4. Store Owner B (other store) tries to view Conversation A -> 403 Forbidden
        $this->actingAs($this->storeOwnerB)
            ->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertStatus(403);

        // 5. Store Owner B tries to post to Conversation A -> 403 Forbidden
        $this->actingAs($this->storeOwnerB)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => 'Pesan dari toko lain',
            ])
            ->assertStatus(403);
    }

    /**
     * Test 5: Polling messages with `since` filter.
     */
    public function test_polling_returns_new_messages_and_marks_them_read(): void
    {
        $conversation = Conversation::create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customerA->id,
            'status' => 'active',
        ]);

        $msg1 = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->customerA->id,
            'body' => 'Pesan 1',
        ]);
        $msg1->created_at = now()->subMinutes(5);
        $msg1->save();

        $timestamp = urlencode(now()->subMinutes(3)->toIso8601String());

        $msg2 = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->customerA->id,
            'body' => 'Pesan 2 (Baru)',
        ]);

        // Poll with `since`
        $response = $this->actingAs($this->storeOwnerA)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages?since={$timestamp}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Pesan 2 (Baru)');

        $msg2->refresh();
        $this->assertNotNull($msg2->read_at);
    }
}
