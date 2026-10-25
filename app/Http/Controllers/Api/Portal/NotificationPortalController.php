<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationPreference;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPortalController extends Controller
{
    /**
     * Format a notification with proper Jakarta timezone serialization.
     */
    protected function formatNotification(Notification $notification): array
    {
        $rawCreated = $notification->getRawOriginal('created_at');
        $rawSent = $notification->getRawOriginal('email_sent_at');
        $rawRead = $notification->getRawOriginal('read_at');

        $status = $notification->read_at ? 'read' : 'unread';

        return [
            'id' => (string) $notification->id,
            'channel' => !empty($notification->delivered_via) ? (is_array($notification->delivered_via) ? implode(',', $notification->delivered_via) : $notification->delivered_via) : 'in_app',
            'event_type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->content,
            'payload' => $notification->data,
            'status' => $status,
            'sent_at' => $rawSent ? Carbon::parse($rawSent, 'Asia/Jakarta')->toIso8601String() : $notification->email_sent_at?->toIso8601String(),
            'read_at' => $rawRead ? Carbon::parse($rawRead, 'Asia/Jakarta')->toIso8601String() : $notification->read_at?->toIso8601String(),
            'created_at' => $rawCreated ? Carbon::parse($rawCreated, 'Asia/Jakarta')->toIso8601String() : $notification->created_at?->toIso8601String(),
        ];
    }

    /**
     * GET /api/v1/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Notification::where('user_id', $user->id);

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        if ($request->filled('channel') && $request->input('channel') !== 'all') {
            $channel = $request->input('channel');
            $query->where(function ($q) use ($channel) {
                $q->whereJsonContains('delivered_via', $channel)
                  ->orWhere('type', 'like', "%{$channel}%");
            });
        }

        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));
        $paginated = $query->latest('created_at')->paginate($perPage);

        $unreadCount = Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        $items = collect($paginated->items())->map(fn ($n) => $this->formatNotification($n))->values();

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $unreadCount,
                'notifications' => $items,
            ],
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * PATCH /api/v1/notifications/{id}/read
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $notification = Notification::where('user_id', $user->id)->findOrFail($id);

        $notification->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $notification->id,
                'status' => 'read',
            ],
        ]);
    }

    /**
     * PATCH /api/v1/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => [
                'updated' => true,
            ],
        ]);
    }

    /**
     * GET /api/v1/notifications/preferences
     */
    public function preferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $userPrefs = NotificationPreference::where('user_id', $user->id)->get();

        $channels = [
            'in_app' => 'Notifikasi Aplikasi',
            'whatsapp' => 'WhatsApp',
            'email' => 'Email',
        ];

        $eventTypes = [
            'order_created' => 'Pesanan Baru Dibuat',
            'order_status_updated' => 'Perubahan Status Pesanan',
            'payment_confirmed' => 'Pembayaran Terkonfirmasi',
            'order_completed' => 'Pesanan Selesai',
        ];

        $groups = [];
        foreach ($channels as $chKey => $chLabel) {
            $events = [];
            foreach ($eventTypes as $evKey => $evLabel) {
                $pref = $userPrefs->first(fn ($p) => $p->channel === $chKey && $p->event_type === $evKey);
                $events[] = [
                    'event_type' => $evKey,
                    'event_label' => $evLabel,
                    'is_enabled' => $pref ? (bool) $pref->is_enabled : true,
                ];
            }
            $groups[] = [
                'channel' => $chKey,
                'channel_label' => $chLabel,
                'events' => $events,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $groups,
        ]);
    }

    /**
     * PUT /api/v1/notifications/preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $request->user();
        $preferences = $request->input('preferences', []);

        foreach ($preferences as $pref) {
            if (isset($pref['channel'], $pref['event_type'])) {
                NotificationPreference::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'channel' => $pref['channel'],
                        'event_type' => $pref['event_type'],
                    ],
                    [
                        'is_enabled' => (bool) ($pref['is_enabled'] ?? true),
                    ]
                );
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'updated' => true,
            ],
        ]);
    }
}
