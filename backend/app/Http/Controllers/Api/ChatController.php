<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChatController extends Controller
{
    use ApiResponse;

    /**
     * Authorize participant access to conversation (FR-CHAT-06).
     */
    protected function authorizeParticipant(Conversation $conversation, User $user): void
    {
        if ($user->hasRole('super_admin')) {
            return;
        }

        // Customer Check
        if ($conversation->customer_id === $user->id) {
            return;
        }

        // Store Admin Check (Must be the owner of the store)
        $store = $conversation->store ?: Store::find($conversation->store_id);
        if ($store && $store->owner_user_id === $user->id) {
            return;
        }

        abort(response()->json([
            'success' => false,
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Anda tidak memiliki hak akses untuk melihat atau berpartisipasi dalam percakapan ini.',
            ],
        ], 403));
    }

    /**
     * Format a chat message resource.
     */
    protected function formatMessage(ChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender?->name,
            'message_type' => $message->message_type,
            'body' => $message->body,
            'attachment_key' => $message->attachment_key,
            'attachment_url' => $message->attachment_key
                ? url("/api/v1/conversations/{$message->conversation_id}/attachments/{$message->id}")
                : null,
            'read_at' => $message->read_at?->toIso8601String(),
            'is_read' => $message->read_at !== null,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /**
     * Format conversation summary resource.
     */
    protected function formatConversation(Conversation $conv, User $currentUser): array
    {
        $otherPartyName = '';
        $otherPartyRole = '';

        if ($currentUser->id === $conv->customer_id) {
            $otherPartyName = $conv->store?->name ?? 'Toko Laundry';
            $otherPartyRole = 'store_admin';
        } else {
            $otherPartyName = $conv->customer?->name ?? 'Pelanggan';
            $otherPartyRole = 'customer';
        }

        $unreadCount = ChatMessage::where('conversation_id', $conv->id)
            ->where('sender_id', '!=', $currentUser->id)
            ->whereNull('read_at')
            ->count();

        $latestMsg = ChatMessage::where('conversation_id', $conv->id)
            ->with('sender')
            ->latest('created_at')
            ->first();

        return [
            'id' => $conv->id,
            'order_id' => $conv->order_id,
            'order_number' => $conv->order?->order_number,
            'customer_id' => $conv->customer_id,
            'customer_name' => $conv->customer?->name,
            'store_id' => $conv->store_id,
            'store_name' => $conv->store?->name,
            'other_party_name' => $otherPartyName,
            'other_party_role' => $otherPartyRole,
            'status' => $conv->status,
            'unread_count' => $unreadCount,
            'last_message' => $latestMsg ? $this->formatMessage($latestMsg) : null,
            'last_message_at' => $conv->last_message_at?->toIso8601String() ?? $conv->updated_at?->toIso8601String(),
            'created_at' => $conv->created_at?->toIso8601String(),
        ];
    }

    /**
     * Get total unread incoming messages count across all conversations belonging to user.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeIds = Store::where('owner_user_id', $user->id)->pluck('id');

        $conversationIds = Conversation::where(function ($q) use ($user, $storeIds) {
            $q->where('customer_id', $user->id);
            if ($storeIds->isNotEmpty()) {
                $q->orWhereIn('store_id', $storeIds);
            }
        })->pluck('id');

        $unreadCount = ChatMessage::whereIn('conversation_id', $conversationIds)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();

        return $this->successResponse(
            data: [
                'unread_count' => $unreadCount,
            ]
        );
    }

    /**
     * List user conversations (FR-CHAT-01, FR-CHAT-06).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Conversation::query()->with(['store', 'customer', 'order']);

        if ($user->hasRole('super_admin')) {
            // Super Admin can view all or filter by store_id
            if ($request->filled('store_id')) {
                $query->where('store_id', $request->query('store_id'));
            }
        } elseif ($user->hasRole('store_admin') && $request->query('context') === 'store') {
            $storeIds = Store::where('owner_user_id', $user->id)->pluck('id');
            $query->whereIn('store_id', $storeIds);
        } else {
            // Default: As customer or combined
            $storeIds = Store::where('owner_user_id', $user->id)->pluck('id');
            $query->where(function ($q) use ($user, $storeIds) {
                $q->where('customer_id', $user->id);
                if ($storeIds->isNotEmpty()) {
                    $q->orWhereIn('store_id', $storeIds);
                }
            });
        }

        $conversations = $query->orderBy('last_message_at', 'desc')->get();
        $data = $conversations->map(fn ($c) => $this->formatConversation($c, $user));

        return $this->successResponse(
            data: $data
        );
    }

    /**
     * Start or Retrieve an Existing Conversation (FR-CHAT-01, FR-CHAT-02).
     */
    public function startOrGet(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
            'order_id' => ['nullable', 'uuid', 'exists:orders,id'],
        ], [
            'store_id.required' => 'ID toko laundry wajib ditentukan.',
            'store_id.exists' => 'Toko laundry tidak ditemukan.',
        ]);

        $storeId = $validated['store_id'];
        $orderId = $validated['order_id'] ?? null;

        // If order_id provided, find conversation for this order first
        $conversation = null;
        if ($orderId) {
            $conversation = Conversation::where('order_id', $orderId)
                ->where('customer_id', $user->id)
                ->first();
        }

        if (!$conversation) {
            $conversation = Conversation::where('store_id', $storeId)
                ->where('customer_id', $user->id)
                ->first();
        }

        if (!$conversation) {
            $conversation = Conversation::create([
                'store_id' => $storeId,
                'customer_id' => $user->id,
                'order_id' => $orderId,
                'status' => 'active',
                'last_message_at' => now(),
            ]);
        } elseif ($orderId && empty($conversation->order_id)) {
            $conversation->order_id = $orderId;
            $conversation->save();
        }

        $conversation->load(['store', 'customer', 'order']);

        return $this->successResponse(
            data: $this->formatConversation($conversation, $user),
            meta: ['message' => 'Percakapan aktif berhasil dimuat.']
        );
    }

    /**
     * Get Conversation Detail & Messages (FR-CHAT-03, FR-CHAT-07, FR-CHAT-08).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::with(['store', 'customer', 'order'])->find($id);

        if (!$conversation) {
            return $this->errorResponse('NOT_FOUND', 'Percakapan tidak ditemukan.', null, 404);
        }

        $this->authorizeParticipant($conversation, $user);

        // Mark incoming unread messages as read
        ChatMessage::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = ChatMessage::where('conversation_id', $conversation->id)
            ->with('sender')
            ->orderBy('created_at', 'asc')
            ->get();

        return $this->successResponse(
            data: [
                'conversation' => $this->formatConversation($conversation, $user),
                'messages' => $messages->map(fn ($m) => $this->formatMessage($m)),
            ]
        );
    }

    /**
     * Mark all incoming unread messages in conversation as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::find($id);

        if (!$conversation) {
            return $this->errorResponse('NOT_FOUND', 'Percakapan tidak ditemukan.', null, 404);
        }

        $this->authorizeParticipant($conversation, $user);

        $affected = ChatMessage::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->successResponse(
            data: [
                'conversation_id' => $conversation->id,
                'marked_read_count' => $affected,
            ],
            meta: [
                'message' => 'Pesan telah ditandai sebagai terbaca.',
            ]
        );
    }

    /**
     * Send Text Message or Upload Photo (FR-CHAT-03, FR-CHAT-04, FR-CHAT-05).
     */
    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::with(['store', 'customer', 'order'])->find($id);

        if (!$conversation) {
            return $this->errorResponse('NOT_FOUND', 'Percakapan tidak ditemukan.', null, 404);
        }

        $this->authorizeParticipant($conversation, $user);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'file', 'image', 'max:5120', 'mimes:jpeg,png,jpg,webp'],
            'attachment' => ['nullable', 'file', 'image', 'max:5120', 'mimes:jpeg,png,jpg,webp'],
        ], [
            'body.max' => 'Pesan teks tidak boleh melebihi 2000 karakter.',
            'image.max' => 'Ukuran foto maksimal adalah 5MB.',
            'image.mimes' => 'Format foto harus berupa JPEG, PNG, JPG, atau WEBP.',
        ]);

        $body = trim($validated['body'] ?? '');
        $file = $request->file('image') ?? $request->file('attachment');

        if (empty($body) && !$file) {
            return $this->errorResponse('VALIDATION_ERROR', 'Pesan teks atau lampiran foto wajib diisi.', null, 422);
        }

        $attachmentKey = null;
        $messageType = 'text';

        if ($file) {
            $messageType = 'image';
            // Save to private local storage
            $path = $file->store("chat_attachments/{$conversation->id}", 'local');
            $attachmentKey = $path;
        }

        $message = DB::transaction(function () use ($conversation, $user, $messageType, $body, $attachmentKey) {
            $msg = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'message_type' => $messageType,
                'body' => $body ?: ($messageType === 'image' ? '[Foto]' : null),
                'attachment_key' => $attachmentKey,
                'read_at' => null,
            ]);

            $conversation->last_message_at = now();
            $conversation->save();

            return $msg;
        });

        // Determine recipient
        $recipientId = ($user->id === $conversation->customer_id)
            ? $conversation->store?->owner_user_id
            : $conversation->customer_id;

        // Dispatch in-app notification to recipient without leaking message body (FR-NOTIF-05)
        if ($recipientId) {
            try {
                Notification::create([
                    'user_id' => $recipientId,
                    'channel' => 'in_app',
                    'event_type' => 'CHAT_MESSAGE_RECEIVED',
                    'title' => 'Pesan Baru dari ' . $user->name,
                    'message' => 'Pesan baru dari ' . $user->name,
                    'payload' => [
                        'conversation_id' => $conversation->id,
                        'message_id' => $message->id,
                        'sender_name' => $user->name,
                    ],
                    'status' => 'unread',
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // Ignore notification failure
            }
        }

        $message->load('sender');

        return $this->successResponse(
            data: $this->formatMessage($message),
            meta: ['message' => 'Pesan berhasil dikirim.'],
            status: 201
        );
    }

    /**
     * Poll new messages in a conversation (FR-CHAT-07, FR-CHAT-08).
     */
    public function getMessages(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::find($id);

        if (!$conversation) {
            return $this->errorResponse('NOT_FOUND', 'Percakapan tidak ditemukan.', null, 404);
        }

        $this->authorizeParticipant($conversation, $user);

        $query = ChatMessage::where('conversation_id', $conversation->id)->with('sender');

        if ($request->filled('since')) {
            try {
                $since = \Carbon\Carbon::parse($request->query('since'));
                $query->where('created_at', '>', $since);
            } catch (\Throwable $e) {
                // Ignore invalid date
            }
        }

        $messages = $query->orderBy('created_at', 'asc')->get();

        // Mark incoming messages as read
        ChatMessage::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->successResponse(
            data: $messages->map(fn ($m) => $this->formatMessage($m))
        );
    }

    /**
     * Stream Private Photo Attachment (FR-CHAT-05, FR-CHAT-06).
     */
    public function streamAttachment(Request $request, string $id, string $messageId)
    {
        $user = $request->user();
        $conversation = Conversation::find($id);

        if (!$conversation) {
            return $this->errorResponse('NOT_FOUND', 'Percakapan tidak ditemukan.', null, 404);
        }

        $this->authorizeParticipant($conversation, $user);

        $message = ChatMessage::where('conversation_id', $conversation->id)->find($messageId);

        if (!$message || !$message->attachment_key) {
            return $this->errorResponse('NOT_FOUND', 'Lampiran foto tidak ditemukan.', null, 404);
        }

        if (!Storage::disk('local')->exists($message->attachment_key)) {
            return $this->errorResponse('NOT_FOUND', 'File foto tidak ditemukan di storage server.', null, 404);
        }

        $path = Storage::disk('local')->path($message->attachment_key);
        $mime = Storage::disk('local')->mimeType($message->attachment_key) ?: 'image/jpeg';

        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
