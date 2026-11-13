<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatbotSessionClearTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroy_all_deletes_all_user_sessions_and_preserves_other_users_and_usage_logs(): void
    {
        $userA = User::factory()->create(['role' => 'customer']);
        $userB = User::factory()->create(['role' => 'customer']);

        $convA1 = AiConversation::create(['user_id' => $userA->id, 'title' => 'Sesi Lama A']);
        $convA2 = AiConversation::create(['user_id' => $userA->id, 'title' => 'Sesi Baru A']);
        $convB1 = AiConversation::create(['user_id' => $userB->id, 'title' => 'Sesi User B']);

        AiMessage::create(['conversation_id' => $convA1->id, 'role' => 'user', 'content' => 'Pesan lama']);
        AiMessage::create(['conversation_id' => $convA2->id, 'role' => 'assistant', 'content' => 'Balasan baru']);
        $msgB = AiMessage::create(['conversation_id' => $convB1->id, 'role' => 'user', 'content' => 'Pesan B']);

        $logA = AiUsageLog::create([
            'user_id' => $userA->id,
            'conversation_id' => $convA2->id,
            'model' => 'liquid/lfm-2.5-2.6b:free',
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30,
            'estimated_cost_usd' => 0,
            'tool_rounds' => 0,
            'status' => 'success',
        ]);

        Sanctum::actingAs($userA, ['*']);

        $res = $this->deleteJson('/api/v1/chatbot/sessions');

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted_count', 2);

        $this->assertDatabaseMissing('ai_conversations', ['id' => $convA1->id]);
        $this->assertDatabaseMissing('ai_conversations', ['id' => $convA2->id]);
        $this->assertDatabaseCount('ai_messages', 1);
        $this->assertDatabaseHas('ai_messages', ['id' => $msgB->id]);
        $this->assertDatabaseHas('ai_conversations', ['id' => $convB1->id]);

        // Usage telemetry stays intact with conversation_id nulled
        $this->assertDatabaseHas('ai_usage_logs', [
            'id' => $logA->id,
            'user_id' => $userA->id,
            'conversation_id' => null,
        ]);

        // Subsequent session list must be completely empty (never resurrect old sessions)
        $this->getJson('/api/v1/chatbot/sessions')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_destroy_all_is_idempotent_when_user_has_no_sessions(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user, ['*']);

        $this->deleteJson('/api/v1/chatbot/sessions')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted_count', 0);
    }

    public function test_destroy_all_requires_authentication(): void
    {
        $this->deleteJson('/api/v1/chatbot/sessions')->assertStatus(401);
    }

    public function test_destroy_single_session_isolates_tenant_and_handles_invalid_id(): void
    {
        $userA = User::factory()->create(['role' => 'customer']);
        $userB = User::factory()->create(['role' => 'customer']);

        $convA = AiConversation::create(['user_id' => $userA->id, 'title' => 'Milik A']);
        $convB = AiConversation::create(['user_id' => $userB->id, 'title' => 'Milik B']);
        AiMessage::create(['conversation_id' => $convA->id, 'role' => 'user', 'content' => 'Halo A']);

        Sanctum::actingAs($userA, ['*']);

        // Cannot delete another user's session or invalid ID
        $this->deleteJson("/api/v1/chatbot/sessions/{$convB->id}")->assertStatus(404);
        $this->deleteJson('/api/v1/chatbot/sessions/not-an-id')->assertStatus(404);
        $this->assertDatabaseHas('ai_conversations', ['id' => $convB->id]);

        // Deleting own session succeeds
        $this->deleteJson("/api/v1/chatbot/sessions/{$convA->id}")->assertStatus(200);
        $this->assertDatabaseMissing('ai_conversations', ['id' => $convA->id]);
    }

    public function test_creating_ai_message_touches_conversation_updated_at_for_session_ordering(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $olderCreated = AiConversation::create(['user_id' => $user->id, 'title' => 'Lebih Lama Dibuat']);
        $newerCreated = AiConversation::create(['user_id' => $user->id, 'title' => 'Lebih Baru Dibuat']);

        $this->travel(5)->seconds();

        // Adding a message to the older conversation should bump its updated_at to newest
        AiMessage::create([
            'conversation_id' => $olderCreated->id,
            'role' => 'user',
            'content' => 'Aktif lagi',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/chatbot/sessions')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', (string) $olderCreated->id);
    }
}
