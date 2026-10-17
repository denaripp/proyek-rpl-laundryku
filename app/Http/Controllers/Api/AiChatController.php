<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiRateLimitException;
use Illuminate\Http\Request;

/**
 * AI chat endpoints. Authenticated users only.
 *
 * AI failures NEVER propagate as 5xx into the marketplace: provider errors
 * degrade to a human-CS fallback (200 + fallback flag), rate/abuse limits
 * return 429. Core transaction endpoints are untouched either way.
 */
class AiChatController extends Controller
{
    public function __construct(protected AiAssistantService $assistant)
    {
    }

    /**
     * POST /api/ai/chat
     */
    public function chat(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:4000',
            'conversation_id' => 'nullable|integer',
            'session_id' => 'nullable|integer',
        ]);

        $conversationId = $validated['conversation_id'] ?? $validated['session_id'] ?? null;

        try {
            $result = $this->assistant->chat(
                $request->user(),
                $conversationId ? (int) $conversationId : null,
                $validated['message']
            );
        } catch (AiRateLimitException $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        }

        return response()->json($result);
    }

    /**
     * GET /api/ai/conversations — user's own conversations only.
     */
    public function index(Request $request)
    {
        $conversations = AiConversation::where('user_id', $request->user()->id)
            ->withCount('messages')
            ->latest()
            ->paginate(15);

        return response()->json($conversations);
    }

    /**
     * GET /api/ai/conversations/{conversation} — tenant isolation:
     * a user can only read their own conversation.
     */
    public function show(Request $request, AiConversation $conversation)
    {
        if ((int) $conversation->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($conversation->load('messages'));
    }
}