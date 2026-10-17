<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatbotMessage;
use App\Models\ChatbotSession;
use App\Models\Order;
use App\Services\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
    protected AiChatService $aiService;

    public function __construct(AiChatService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Get list of FAQ suggestions and quick prompts (Public or Authenticated).
     */
    public function faq(): JsonResponse
    {
        return $this->getFaq();
    }

    /**
     * Get list of general-purpose FAQs.
     */
    public function getFaq(): JsonResponse
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
     * List user's chatbot sessions.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $sessions = ChatbotSession::where('user_id', $user->id)
            ->with(['latestMessage'])
            ->withCount('messages')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    /**
     * Create a new chatbot session.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => 'nullable|string|max:150',
            'context_type' => 'nullable|string|max:50',
            'context_id' => 'nullable|uuid',
            'message' => 'nullable|string|max:2000',
        ]);

        // Validate context_id if it's an order
        if (($validated['context_type'] ?? '') === 'order' && !empty($validated['context_id'])) {
            $order = Order::where('id', $validated['context_id'])
                ->where('customer_id', $user->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'ORDER_NOT_FOUND',
                        'message' => 'Pesanan tidak ditemukan atau bukan milik akun Anda.',
                    ],
                ], 404);
            }
        }

        $session = ChatbotSession::create([
            'user_id' => $user->id,
            'title' => $validated['title'] ?? ($validated['message'] ? Str::limit($validated['message'], 40) : 'Konsultasi LaundryKu'),
            'context_type' => $validated['context_type'] ?? 'general',
            'context_id' => $validated['context_id'] ?? null,
            'provider' => config('ai.provider', config('services.ai.provider', 'openai')),
        ]);

        // If an initial message was provided, process it
        if (!empty($validated['message'])) {
            $userMessage = ChatbotMessage::create([
                'session_id' => $session->id,
                'role' => 'user',
                'content' => $validated['message'],
            ]);

            $aiResponse = $this->aiService->chat([
                ['role' => 'user', 'content' => $validated['message']],
            ], $user);

            $assistantMessage = ChatbotMessage::create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => $aiResponse['content'],
                'token_count' => $aiResponse['token_count'],
                'metadata' => [
                    'is_fallback' => $aiResponse['is_fallback'],
                    'provider' => $aiResponse['provider'],
                ],
            ]);

            $session->touch();
            $session->load('messages');

            return response()->json([
                'success' => true,
                'data' => $session,
            ], 201);
        }

        return response()->json([
            'success' => true,
            'data' => $session,
        ], 201);
    }

    /**
     * Get details of a single chatbot session with its messages.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $session = ChatbotSession::with(['messages'])->find($id);

        if (!$session || $session->user_id !== $user->id) {
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
            'data' => $session,
        ]);
    }

    /**
     * Send a message within an existing chatbot session.
     */
    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $session = ChatbotSession::find($id);

        if (!$session || $session->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SESSION_NOT_FOUND',
                    'message' => 'Sesi percakapan AI tidak ditemukan atau Anda tidak memiliki akses.',
                ],
            ], 404);
        }

        $validated = $request->validate([
            'message' => 'required|string|min:1|max:2000',
        ]);

        $content = trim($validated['message']);

        // 1. Store User Message
        $userMsg = ChatbotMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => $content,
        ]);

        // 2. Fetch recent conversation history for context window (last 10 messages)
        $history = ChatbotMessage::where('session_id', $session->id)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->reverse()
            ->map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content,
            ])
            ->values()
            ->toArray();

        // 3. Request AI Response via AiChatService (Read-Only Grounded)
        $aiResult = $this->aiService->chat($history, $user);

        // 4. Store Assistant Response
        $assistantMsg = ChatbotMessage::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => $aiResult['content'],
            'token_count' => $aiResult['token_count'],
            'metadata' => [
                'is_fallback' => $aiResult['is_fallback'],
                'provider' => $aiResult['provider'],
            ],
        ]);

        // Update session title if default
        if ($session->title === 'Konsultasi LaundryKu' || empty($session->title)) {
            $session->title = Str::limit($content, 40);
        }
        $session->touch();

        return response()->json([
            'success' => true,
            'data' => [
                'user_message' => $userMsg,
                'assistant_message' => $assistantMsg,
            ],
        ]);
    }

    /**
     * Delete/Clear a chatbot session.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $session = ChatbotSession::find($id);

        if (!$session || $session->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SESSION_NOT_FOUND',
                    'message' => 'Sesi percakapan AI tidak ditemukan atau Anda tidak memiliki akses.',
                ],
            ], 404);
        }

        $session->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesi chatbot berhasil dihapus.',
        ]);
    }

    /**
     * Public Guest Question Endpoint (without persistent session).
     */
    public function guestAsk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|min:1|max:2000',
            'messages' => 'nullable|array',
            'messages.*.role' => 'required_with:messages|string|in:user,assistant,system',
            'messages.*.content' => 'required_with:messages|string|max:4000',
        ]);

        $content = trim($validated['message']);

        $historyMessages = [];
        if (!empty($validated['messages']) && is_array($validated['messages'])) {
            foreach ($validated['messages'] as $m) {
                if (isset($m['role'], $m['content'])) {
                    $historyMessages[] = [
                        'role' => $m['role'],
                        'content' => (string) $m['content'],
                    ];
                }
            }
        }

        // If history does not contain the current message at the end, append it
        $lastMsg = end($historyMessages);
        if (!$lastMsg || ($lastMsg['role'] ?? '') !== 'user' || ($lastMsg['content'] ?? '') !== $content) {
            $historyMessages[] = ['role' => 'user', 'content' => $content];
        }

        $user = $request->user('sanctum') ?? $request->user();
        $aiResult = $this->aiService->chat($historyMessages, $user);

        return response()->json([
            'success' => true,
            'data' => [
                'question' => $content,
                'answer' => $aiResult['content'],
                'is_fallback' => $aiResult['is_fallback'],
                'provider' => $aiResult['provider'],
            ],
        ]);
    }
}
