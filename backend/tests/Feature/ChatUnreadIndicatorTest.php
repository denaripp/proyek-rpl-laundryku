<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatUnreadIndicatorTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $otherCustomer;
    protected User $storeOwner;
    protected Store $store;
    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $customerRole = Role::firstOrCreate(['id' => 1, 'code' => 'customer'], ['name' => 'Customer']);
        $storeAdminRole = Role::firstOrCreate(['id' => 3, 'code' => 'store_admin'], ['name' => 'Admin Toko']);

        // Customer
        $this->customer = User::factory()->create([
            'name' => 'Siti Pelanggan',
            'email' => 'siti@example.com',
            'phone' => '628111222333',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->customer->id]);
        UserRole::create(['user_id' => $this->customer->id, 'role_id' => $customerRole->id, 'status' => 'active']);

        // Other Customer
        $this->otherCustomer = User::factory()->create([
            'name' => 'Rudi Pelanggan Lain',
            'email' => 'rudi@example.com',
            'phone' => '628999888777',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->otherCustomer->id]);
        UserRole::create(['user_id' => $this->otherCustomer->id, 'role_id' => $customerRole->id, 'status' => 'active']);

        // Store Owner
        $this->storeOwner = User::factory()->create([
            'name' => 'Bambang Owner Laundry',
            'email' => 'bambang@example.com',
            'phone' => '628555666777',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->storeOwner->id]);
        UserRole::create(['user_id' => $this->storeOwner->id, 'role_id' => $storeAdminRole->id, 'status' => 'active']);

        // Store
        $this->store = Store::create([
            'owner_user_id' => $this->storeOwner->id,
            'name' => 'Kilau Laundry Sentosa',
            'description' => 'Layanan laundry premium cepat dan higienis.',
            'address_text' => 'Jl. Merdeka No. 45, Bandung',
            'latitude' => -6.9175,
            'longitude' => 107.6191,
            'service_radius_km' => 7.0,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        // Conversation between Customer & Store
        $this->conversation = Conversation::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => 'active',
            'last_message_at' => now(),
        ]);
    }

    public function test_unread_count_is_zero_initially(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/conversations/unread-count');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'unread_count' => 0,
                ],
            ]);
    }

    public function test_incoming_chat_message_increments_recipient_unread_count_while_sender_stays_zero(): void
    {
        // 1. Store Owner sends message to Customer
        $sendResponse = $this->actingAs($this->storeOwner, 'sanctum')
            ->postJson("/api/v1/conversations/{$this->conversation->id}/messages", [
                'body' => 'Halo Kak Siti, cucian Anda sudah siap diantar.',
            ]);
        $sendResponse->assertStatus(201);

        // 2. Recipient (Customer) unread count is 1
        $customerUnread = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/conversations/unread-count');
        $customerUnread->assertStatus(200)
            ->assertJsonPath('data.unread_count', 1);

        // 3. Sender (Store Owner) unread count is 0 (own messages do not count)
        $ownerUnread = $this->actingAs($this->storeOwner, 'sanctum')
            ->getJson('/api/v1/conversations/unread-count');
        $ownerUnread->assertStatus(200)
            ->assertJsonPath('data.unread_count', 0);

        // 4. Other customer unread count is 0 (isolated)
        $otherUnread = $this->actingAs($this->otherCustomer, 'sanctum')
            ->getJson('/api/v1/conversations/unread-count');
        $otherUnread->assertStatus(200)
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_opening_conversation_detail_marks_messages_as_read_and_decrements_unread_count(): void
    {
        // Store Owner sends 2 messages
        ChatMessage::create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->storeOwner->id,
            'message_type' => 'text',
            'body' => 'Pesan 1',
            'read_at' => null,
        ]);
        ChatMessage::create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->storeOwner->id,
            'message_type' => 'text',
            'body' => 'Pesan 2',
            'read_at' => null,
        ]);

        // Verify unread count is 2 for customer
        $unreadBefore = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/conversations/unread-count');
        $unreadBefore->assertJsonPath('data.unread_count', 2);

        // Customer opens conversation detail
        $showResponse = $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/v1/conversations/{$this->conversation->id}");
        $showResponse->assertStatus(200)
            ->assertJsonPath('data.conversation.unread_count', 0);

        // Unread count is now 0
        $unreadAfter = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/conversations/unread-count');
        $unreadAfter->assertJsonPath('data.unread_count', 0);
    }

    public function test_mark_as_read_endpoint_explicitly_clears_unread(): void
    {
        ChatMessage::create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->customer->id,
            'message_type' => 'text',
            'body' => 'Halo apakah buka hari ini?',
            'read_at' => null,
        ]);

        // Store Owner has 1 unread
        $ownerUnread = $this->actingAs($this->storeOwner, 'sanctum')
            ->getJson('/api/v1/conversations/unread-count');
        $ownerUnread->assertJsonPath('data.unread_count', 1);

        // Store Owner calls markAsRead
        $markResponse = $this->actingAs($this->storeOwner, 'sanctum')
            ->postJson("/api/v1/conversations/{$this->conversation->id}/read");
        $markResponse->assertStatus(200)
            ->assertJsonPath('data.marked_read_count', 1);

        // Unread is now 0
        $ownerUnreadAfter = $this->actingAs($this->storeOwner, 'sanctum')
            ->getJson('/api/v1/conversations/unread-count');
        $ownerUnreadAfter->assertJsonPath('data.unread_count', 0);
    }

    public function test_unauthorized_user_cannot_access_or_mark_other_users_conversation(): void
    {
        $response = $this->actingAs($this->otherCustomer, 'sanctum')
            ->getJson("/api/v1/conversations/{$this->conversation->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_chat_notification_does_not_leak_message_body_content(): void
    {
        $secretMessage = 'Kunci rahasia pintu belakang 123456';

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/conversations/{$this->conversation->id}/messages", [
                'body' => $secretMessage,
            ]);

        $notification = Notification::where('user_id', $this->storeOwner->id)
            ->where('event_type', 'CHAT_MESSAGE_RECEIVED')
            ->latest('id')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('Pesan Baru dari Siti Pelanggan', $notification->title);
        $this->assertEquals('Pesan baru dari Siti Pelanggan', $notification->message);
        $this->assertStringNotContainsString($secretMessage, $notification->message);
    }
}
