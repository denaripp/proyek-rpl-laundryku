<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiUsageLog;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiRateLimitException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
    public function __construct(protected AiAssistantService $assistant)
    {
    }

    /**
     * Get FAQ list
     */
    public function faq(): JsonResponse
    {
        $faqs = [
            [
                'id' => 'how_to_order',
                'question' => 'Bagaimana cara pesan laundry?',
                'answer' => 'Pilih toko di menu Cari Toko (/stores), tentukan layanan, masukkan alamat penjemputan, lalu klik Pesan Sekarang.',
                'category' => 'customer',
                'icon' => 'shopping-bag',
            ],
            [
                'id' => 'check_order_status',
                'question' => 'Cek status pesanan aktif',
                'answer' => 'Buka menu Pesanan Saya (/orders) untuk memantau status cucian, kurir penjemput/pengantar, dan rincian pembayaran.',
                'category' => 'customer',
                'icon' => 'truck',
            ],
            [
                'id' => 'price_system',
                'question' => 'Mengapa ada estimasi dan harga final?',
                'answer' => 'Estimasi adalah perkiraan awal. Harga final ditentukan setelah pakaian ditimbang oleh toko dan wajib disetujui pelanggan.',
                'category' => 'harga',
                'icon' => 'scale',
            ],
            [
                'id' => 'payment_methods',
                'question' => 'Metode pembayaran apa saja yang tersedia?',
                'answer' => 'LaundryKu mendukung QRIS instan, Virtual Account bank (BCA, Mandiri, BNI, BRI), dan COD (bayar tunai ke kurir).',
                'category' => 'pembayaran',
                'icon' => 'credit-card',
            ],
            [
                'id' => 'join_partner_store',
                'question' => 'Bagaimana cara jadi mitra toko?',
                'answer' => 'Buka menu Gabung Mitra (/upgrade) -> Pendaftaran Toko, lengkapi data outlet & dokumen usaha untuk verifikasi admin.',
                'category' => 'mitra',
                'icon' => 'sparkles',
            ],
            [
                'id' => 'join_courier',
                'question' => 'Bagaimana cara mendaftar jadi kurir?',
                'answer' => 'Buka menu Gabung Mitra (/upgrade) -> Pengajuan Kurir, lengkapi data diri, SIM C, KTP, dan info kendaraan.',
                'category' => 'kurir',
                'icon' => 'truck',
            ],
            [
                'id' => 'file_complaint',
                'question' => 'Bagaimana cara mengajukan komplain cucian?',
                'answer' => 'Buka menu Pesanan Saya (/orders) -> Detail Pesanan -> klik Ajukan Komplain maksimal 1x24 jam setelah pesanan selesai disertai foto bukti.',
                'category' => 'bantuan',
                'icon' => 'alert-circle',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $faqs,
            'message' => 'FAQ berhasil dimuat',
        ]);
    }

    /**
     * List user's chatbot sessions
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = AiConversation::where('user_id', $user->id)
            ->with(['messages'])
            ->latest('updated_at')
            ->latest('id')
            ->get();

        $data = $conversations->map(fn ($c) => $this->formatSession($c))->values()->all();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Create a new chatbot session
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => 'nullable|string|max:150',
            'message' => 'nullable|string|max:4000',
        ]);

        $title = $validated['title'] ?? ($validated['message'] ? Str::limit($validated['message'], 40) : 'Percakapan Asisten AI');

        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'title' => $title,
        ]);

        if (!empty($validated['message'])) {
            try {
                $this->assistant->chat($user, $conversation->id, $validated['message']);
            } catch (AiRateLimitException $e) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'RATE_LIMIT_EXCEEDED', 'message' => $e->getMessage()],
                ], 429);
            }
        }

        $conversation->load('messages');

        return response()->json([
            'success' => true,
            'data' => $this->formatSession($conversation),
        ], 201);
    }

    /**
     * Get detail of a single session
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $conversation = AiConversation::with('messages')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SESSION_NOT_FOUND',
                    'message' => 'Sesi percakapan AI tidak ditemukan atau Anda tidak memiliki akses.',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatSession($conversation),
        ]);
    }

    /**
     * Send a message within a session
     */
    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $conversation = AiConversation::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SESSION_NOT_FOUND',
                    'message' => 'Sesi percakapan AI tidak ditemukan atau Anda tidak memiliki akses.',
                ],
            ], 404);
        }

        $validated = $request->validate([
            'message' => 'required|string|min:1|max:4000',
        ]);

        try {
            $result = $this->assistant->chat($user, $conversation->id, $validated['message']);
        } catch (AiRateLimitException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'RATE_LIMIT_EXCEEDED', 'message' => $e->getMessage()],
            ], 429);
        }

        $userMsg = AiMessage::where('conversation_id', $conversation->id)
            ->where('role', 'user')
            ->latest('id')
            ->first();

        $assistantMsg = AiMessage::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->latest('id')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'user_message' => $userMsg ? $this->formatMessage($userMsg) : null,
                'assistant_message' => $assistantMsg ? $this->formatMessage($assistantMsg) : null,
            ],
        ]);
    }

    /**
     * Delete all chatbot sessions owned by the authenticated user.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $deletedCount = $this->deleteConversationsForUser((int) $request->user()->id);

        return response()->json([
            'success' => true,
            'data' => [
                'deleted_count' => $deletedCount,
            ],
            'message' => 'Seluruh riwayat percakapan AI berhasil dihapus.',
        ]);
    }

    /**
     * Delete a session
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        if (!ctype_digit($id)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SESSION_NOT_FOUND',
                    'message' => 'Sesi percakapan AI tidak ditemukan atau Anda tidak memiliki akses.',
                ],
            ], 404);
        }

        $deleted = $this->deleteConversationsForUser((int) $request->user()->id, (int) $id);

        if ($deleted === 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SESSION_NOT_FOUND',
                    'message' => 'Sesi percakapan AI tidak ditemukan atau Anda tidak memiliki akses.',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesi percakapan berhasil dihapus.',
        ]);
    }

    private function deleteConversationsForUser(int $userId, ?int $conversationId = null): int
    {
        return DB::transaction(function () use ($userId, $conversationId) {
            $query = AiConversation::where('user_id', $userId);
            if ($conversationId !== null) {
                $query->where('id', $conversationId);
            }

            $ids = $query->pluck('id');
            if ($ids->isEmpty()) {
                return 0;
            }

            // Preserve telemetry & rate-limit history while releasing FK references safely
            AiUsageLog::whereIn('conversation_id', $ids)->update(['conversation_id' => null]);
            AiMessage::whereIn('conversation_id', $ids)->delete();

            return AiConversation::where('user_id', $userId)
                ->whereIn('id', $ids)
                ->delete();
        });
    }

    /**
     * Public Guest Ask
     */
    public function guestAsk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|min:1|max:4000',
        ]);

        $message = trim($validated['message']);
        $result = $this->assistant->replyForGuest($message, $request->ip());

        return response()->json([
            'success' => true,
            'data' => [
                'question' => $message,
                'answer' => $result['answer'],
                'is_fallback' => $result['is_fallback'],
                'provider' => $result['provider'],
            ],
        ]);
    }

    private function formatMessage(AiMessage $msg): array
    {
        $isFallback = false;
        if (is_array($msg->metadata) && isset($msg->metadata['fallback'])) {
            $isFallback = (bool) $msg->metadata['fallback'];
        }

        $metadata = [
            'is_fallback' => $isFallback,
            'provider' => $msg->model ?? 'laundryku_ai',
        ];
        if (is_array($msg->metadata) && !empty($msg->metadata['resolved_model'])) {
            $metadata['resolved_model'] = $msg->metadata['resolved_model'];
        }

        return [
            'id' => (string) $msg->id,
            'session_id' => (string) $msg->conversation_id,
            'role' => $msg->role,
            'content' => $msg->content,
            'metadata' => $metadata,
            'created_at' => $msg->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }

    private function formatSession(AiConversation $conv): array
    {
        $messages = $conv->relationLoaded('messages')
            ? $conv->messages
            : $conv->messages()->orderBy('id', 'asc')->get();

        $latest = $messages->last();

        return [
            'id' => (string) $conv->id,
            'user_id' => (string) $conv->user_id,
            'title' => $conv->title ?? 'Percakapan Asisten AI',
            'context_type' => 'general',
            'context_id' => null,
            'provider' => config('ai.provider', 'openrouter'),
            'messages_count' => $messages->count(),
            'latest_message' => $latest ? $this->formatMessage($latest) : null,
            'messages' => $messages->map(fn ($m) => $this->formatMessage($m))->values()->all(),
            'created_at' => $conv->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'updated_at' => $conv->updated_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}
