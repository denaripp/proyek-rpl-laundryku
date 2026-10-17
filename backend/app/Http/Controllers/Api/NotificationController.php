<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * List multi-channel notifications for authenticated user (PRD 11.8, FR-NOTIF).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $unreadOnly = $request->boolean('unread_only');
        $channel = $request->query('channel'); // in_app, whatsapp, email, all

        $query = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($unreadOnly) {
            $query->where('status', 'unread');
        }

        if (!empty($channel) && $channel !== 'all') {
            $query->where('channel', $channel);
        }

        $perPage = (int) $request->query('per_page', 30);
        $notifications = $query->paginate($perPage);

        $unreadCount = Notification::where('user_id', $user->id)
            ->where('status', 'unread')
            ->count();

        $items = collect($notifications->items())->map(fn ($n) => [
            'id' => $n->id,
            'channel' => $n->channel,
            'event_type' => $n->event_type,
            'title' => $n->title,
            'message' => $n->message,
            'payload' => $n->payload,
            'status' => $n->status,
            'sent_at' => $n->sent_at?->toIso8601String(),
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
        ]);

        return $this->successResponse(
            data: [
                'unread_count' => $unreadCount,
                'notifications' => $items,
            ],
            meta: [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ]
        );
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$notification) {
            return $this->errorResponse('NOT_FOUND', 'Notifikasi tidak ditemukan.', null, 404);
        }

        $notification->status = 'read';
        $notification->read_at = now();
        $notification->save();

        return $this->successResponse([
            'id' => $notification->id,
            'status' => 'read',
            'read_at' => $notification->read_at->toIso8601String(),
        ], ['message' => 'Notifikasi ditandai telah dibaca.']);
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('status', 'unread')
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);

        return $this->successResponse(
            ['updated' => true],
            ['message' => 'Semua notifikasi ditandai telah dibaca.']
        );
    }

    /**
     * Get user notification channel preferences (FR-NOTIF).
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $channels = ['in_app', 'whatsapp', 'email'];
        $events = [
            'ORDER_CREATED' => 'Pesanan Baru Dibuat',
            'ORDER_STATUS_CHANGED' => 'Pembaruan Status Penjemputan / Pengantaran',
            'PRICE_ESTIMATED' => 'Persetujuan Harga Final & Timbangan',
            'PAYMENT_SUCCESS' => 'Konfirmasi Pembayaran Berhasil',
            'ORDER_COMPLETED' => 'Pesanan Selesai & Ulasan',
            'COMPLAINT_UPDATED' => 'Pembaruan Status Komplain & Solusi',
        ];

        $savedPrefs = NotificationPreference::where('user_id', $user->id)->get();

        $preferences = [];
        foreach ($channels as $ch) {
            $preferences[$ch] = [
                'channel' => $ch,
                'channel_label' => match ($ch) {
                    'in_app' => 'Notifikasi In-App (Aplikasi)',
                    'whatsapp' => 'WhatsApp Gateway',
                    'email' => 'Email Pemberitahuan',
                    default => $ch,
                },
                'events' => [],
            ];

            foreach ($events as $evtKey => $evtLabel) {
                $saved = $savedPrefs->first(fn ($p) => $p->channel === $ch && $p->event_type === $evtKey);
                $fallback = $savedPrefs->first(fn ($p) => $p->channel === $ch && $p->event_type === 'all');

                $isEnabled = $saved ? (bool) $saved->is_enabled : ($fallback ? (bool) $fallback->is_enabled : true);

                $preferences[$ch]['events'][] = [
                    'event_type' => $evtKey,
                    'event_label' => $evtLabel,
                    'is_enabled' => $isEnabled,
                ];
            }
        }

        return $this->successResponse(
            data: array_values($preferences)
        );
    }

    /**
     * Update user notification preferences (FR-NOTIF).
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*.channel' => ['required', 'string', 'in:in_app,whatsapp,email'],
            'preferences.*.event_type' => ['required', 'string'],
            'preferences.*.is_enabled' => ['required', 'boolean'],
        ], [
            'preferences.required' => 'Daftar preferensi notifikasi wajib disertakan.',
            'preferences.*.channel.in' => 'Kanal notifikasi hanya mendukung in_app, whatsapp, atau email.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Input preferensi tidak valid.', $validator->errors()->toArray(), 422);
        }

        foreach ($request->input('preferences') as $pref) {
            NotificationPreference::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'channel' => $pref['channel'],
                    'event_type' => $pref['event_type'],
                ],
                [
                    'is_enabled' => (bool) $pref['is_enabled'],
                ]
            );
        }

        return $this->successResponse(
            data: ['updated' => true],
            meta: ['message' => 'Preferensi notifikasi berhasil diperbarui.']
        );
    }
}
