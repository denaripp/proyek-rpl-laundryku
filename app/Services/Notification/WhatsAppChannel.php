<?php

namespace App\Services\Notification;

use App\Jobs\SendWhatsAppJob;
use App\Models\Notification;
use App\Models\WhatsAppLog;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel implements NotificationChannel
{
    public function key(): string
    {
        return 'whatsapp';
    }

    public function send(Notification $notification): bool
    {
        // Jika enabled=false -> skip diam-diam (tanpa error, tanpa log)
        if (! config('services.whatsapp.enabled', false)) {
            return false;
        }

        $user = $notification->user;
        if (! $user) {
            return false;
        }

        try {
            // Penerima owner toko: pakai stores.whatsapp, fallback stores.phone, fallback users.phone
            $rawPhone = WhatsAppService::resolveRecipientPhone($user, $notification->data ?? []);
            $normalizedPhone = WhatsAppService::normalizePhone($rawPhone);

            // Jika nomor kosong/tidak valid -> catat whatsapp_logs status 'skipped', JANGAN lempar exception
            if (empty($normalizedPhone)) {
                WhatsAppLog::create([
                    'recipient_phone'  => $rawPhone ?: '-',
                    'recipient_role'   => $user->role ?? null,
                    'user_id'          => $user->id,
                    'event'            => $notification->type,
                    'message'          => $notification->content,
                    'driver'           => config('services.whatsapp.driver', 'log'),
                    'status'           => 'skipped',
                    'error'            => 'Nomor WhatsApp kosong atau tidak valid.',
                    'response_payload' => null,
                ]);

                return false;
            }

            $message = WhatsAppService::buildMessage($notification);

            SendWhatsAppJob::dispatch(
                $normalizedPhone,
                $message,
                $notification->type,
                $user->id,
                $user->role,
                [
                    'notification_id' => $notification->id,
                    'order_id' => $notification->data['order_id'] ?? null,
                ]
            );

            return true;
        } catch (\Throwable $e) {
            // Kegagalan WA TIDAK BOLEH memengaruhi channel in-app/email maupun transaksi
            Log::error('WhatsAppChannel send error: ' . $e->getMessage(), [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
            ]);

            return false;
        }
    }
}
