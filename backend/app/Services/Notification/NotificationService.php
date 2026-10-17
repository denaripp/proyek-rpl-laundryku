<?php

namespace App\Services\Notification;

use App\Jobs\SendNotificationJob;
use App\Models\Complaint;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    /**
     * Dispatch multi-channel notifications to a user.
     * Guarantees: Notification failure NEVER breaks or cancels the calling transaction (FR-NOTIF).
     *
     * @param User|string $user Target user model or User UUID
     * @param string $eventType Event identifier (e.g. ORDER_CREATED, ORDER_STATUS_CHANGED, etc.)
     * @param string $title Notification title
     * @param string $message Notification body message
     * @param array|null $payload Additional event payload metadata
     * @param array $channels List of target channels ('in_app', 'whatsapp', 'email')
     * @return Notification[] Created notification models
     */
    public function send(
        User|string $user,
        string $eventType,
        string $title,
        string $message,
        ?array $payload = null,
        array $channels = ['in_app', 'whatsapp', 'email']
    ): array {
        $userId = $user instanceof User ? $user->id : $user;
        $userModel = $user instanceof User ? $user : User::find($userId);

        if (!$userModel) {
            Log::warning("[NOTIFICATION SERVICE] Target user not found: {$userId}");
            return [];
        }

        $createdNotifications = [];

        foreach ($channels as $channel) {
            try {
                // Check if user has enabled this channel for this event type
                if (!NotificationPreference::isEnabled($userModel, $channel, $eventType)) {
                    Log::info("[NOTIFICATION SKIPPED] User {$userId} disabled channel '{$channel}' for event '{$eventType}'");
                    continue;
                }

                $notification = Notification::create([
                    'user_id' => $userId,
                    'channel' => $channel,
                    'event_type' => $eventType,
                    'title' => $title,
                    'message' => $message,
                    'payload' => $payload,
                    'status' => 'unread',
                    'sent_at' => null,
                ]);

                // Dispatch via queue safely
                SendNotificationJob::dispatch($notification);

                $createdNotifications[] = $notification;
            } catch (Throwable $e) {
                // Never rethrow to preserve transaction integrity
                Log::error("[NOTIFICATION DISPATCH FAILED] Channel: {$channel} | User: {$userId} | Error: {$e->getMessage()}", [
                    'exception' => $e,
                    'event_type' => $eventType,
                ]);
            }
        }

        return $createdNotifications;
    }

    /**
     * Helper: Notify OTP generation (FR-AUTH, FR-NOTIF-04).
     * OTP must only be sent via the designated registration channel and never as an in-app notification or containing plain OTP.
     */
    public function notifyOtp(User $user, string $channel, string $destination, string $otpCode): void
    {
        // OTP must NEVER appear as an in-app notification (FR-NOTIF-04).
        // Registration OTP dispatch is handled directly by OtpService to the chosen registration channel.
        Log::info('[NOTIFICATION SERVICE] Registration OTP dispatched via ' . $channel . ' for user ' . $user->id);
    }

    /**
     * Helper: Notify when an order is created (FR-ORDER-01, FR-NOTIF).
     * Notifies customer and store owner.
     */
    public function notifyOrderCreated(Order $order): void
    {
        $order->loadMissing(['store.owner', 'customer']);

        // 1. Notify Customer
        if ($order->customer_id) {
            $this->send(
                user: $order->customer_id,
                eventType: 'ORDER_CREATED',
                title: 'Pesanan Berhasil Dibuat',
                message: "Pesanan laundry Anda #{$order->order_number} di toko {$order->store?->name} berhasil dibuat. Kurir akan segera ditugaskan untuk menjemput cucian Anda.",
                payload: ['order_id' => $order->id, 'order_number' => $order->order_number, 'store_id' => $order->store_id],
                channels: ['in_app', 'whatsapp', 'email']
            );
        }

        // 2. Notify Store Owner
        if ($order->store?->owner_user_id) {
            $this->send(
                user: $order->store->owner_user_id,
                eventType: 'ORDER_CREATED',
                title: 'Pesanan Masuk Baru',
                message: "Toko {$order->store->name} menerima pesanan baru #{$order->order_number} dari {$order->customer?->name}.",
                payload: ['order_id' => $order->id, 'order_number' => $order->order_number, 'customer_id' => $order->customer_id],
                channels: ['in_app', 'whatsapp']
            );
        }
    }

    /**
     * Helper: Notify when order status transitions (FR-ORDER, FR-TRACK, FR-NOTIF).
     */
    public function notifyOrderStatusChanged(Order $order, ?string $fromStatus, string $toStatus, ?string $note = null): void
    {
        $order->loadMissing(['store', 'customer', 'courier']);

        $statusTitles = [
            'PICKUP_ASSIGNED' => 'Kurir Sedang Menuju Lokasi Anda',
            'PICKED_UP' => 'Cucian Berhasil Dijemput',
            'AT_STORE' => 'Cucian Telah Tiba di Outlet Toko',
            'AWAITING_PRICE_APPROVAL' => 'Rincian Berat & Harga Final Siap Disetujui',
            'PRICE_APPROVED' => 'Harga Final Telah Disetujui',
            'AWAITING_PAYMENT' => 'Menunggu Pembayaran Pesanan',
            'IN_WASH' => 'Cucian Anda Sedang Dicuci & Diproses',
            'WASHED' => 'Pencucian Selesai, Menunggu Pengeringan / Setrika',
            'READY_FOR_DELIVERY' => 'Cucian Siap Diantar Kembali',
            'DELIVERY_ASSIGNED' => 'Kurir Sedang Mengantar Cucian ke Alamat Anda',
            'DELIVERED' => 'Cucian Telah Tiba di Alamat Pengantaran',
            'COMPLETED' => 'Pesanan Laundry Telah Selesai',
            'CANCELLED' => 'Pesanan Telah Dibatalkan',
        ];

        $title = $statusTitles[$toStatus] ?? "Status Pesanan Diperbarui: {$toStatus}";
        $message = "Status pesanan #{$order->order_number} telah diperbarui ke {$toStatus}." . ($note ? " Catatan: {$note}" : "");

        if ($order->customer_id) {
            $this->send(
                user: $order->customer_id,
                eventType: 'ORDER_STATUS_CHANGED',
                title: $title,
                message: $message,
                payload: [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                ],
                channels: ['in_app', 'whatsapp']
            );
        }
    }

    /**
     * Helper: Notify when final price is calculated and awaits customer approval (FR-PRICE-05, US-CUS-07).
     */
    public function notifyPriceEstimated(Order $order): void
    {
        if (!$order->customer_id) return;

        $amountFormatted = 'Rp ' . number_format($order->final_amount ?: $order->estimated_amount, 0, ',', '.');
        $weightFormatted = $order->actual_weight_kg ? "{$order->actual_weight_kg} kg" : "estimasi";

        $this->send(
            user: $order->customer_id,
            eventType: 'PRICE_ESTIMATED',
            title: 'Konfirmasi Harga Final Cucian',
            message: "Cucian pesanan #{$order->order_number} telah ditimbang ({$weightFormatted}) dengan total tagihan {$amountFormatted}. Mohon setujui rincian harga untuk memulai proses pencucian.",
            payload: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'final_amount' => $order->final_amount,
                'actual_weight_kg' => $order->actual_weight_kg,
            ],
            channels: ['in_app', 'whatsapp', 'email']
        );
    }

    /**
     * Helper: Notify when payment is successfully confirmed (FR-PAY-05, US-CUS-08).
     */
    public function notifyPaymentSuccess(Order $order, $payment = null): void
    {
        $amount = $payment ? (int) $payment->amount : (int) ($order->final_amount ?: $order->estimated_amount);
        $amountFormatted = 'Rp ' . number_format($amount, 0, ',', '.');

        if ($order->customer_id) {
            $this->send(
                user: $order->customer_id,
                eventType: 'PAYMENT_SUCCESS',
                title: 'Pembayaran Berhasil Dikonfirmasi',
                message: "Pembayaran sebesar {$amountFormatted} untuk pesanan #{$order->order_number} telah berhasil diverifikasi. Toko akan segera memproses cucian Anda.",
                payload: [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'amount' => $amount,
                ],
                channels: ['in_app', 'whatsapp', 'email']
            );
        }
    }

    /**
     * Helper: Notify when order is completed and invite for rating (FR-RATE-01, US-CUS-09).
     */
    public function notifyOrderCompleted(Order $order): void
    {
        if ($order->customer_id) {
            $this->send(
                user: $order->customer_id,
                eventType: 'ORDER_COMPLETED',
                title: 'Pesanan Selesai',
                message: "Pesanan #{$order->order_number} telah selesai. Anda dapat memberikan penilaian dan ulasan layanan.",
                payload: [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'store_id' => $order->store_id,
                ],
                channels: ['in_app', 'whatsapp']
            );
        }
    }

    /**
     * Helper: Notify when a complaint is created or status updated (FR-COM-01..03).
     */
    public function notifyComplaintUpdated(Complaint $complaint, string $status, ?string $resolutionNote = null): void
    {
        $complaint->loadMissing(['order', 'store.owner', 'customer']);

        if ($status === 'OPEN') {
            // Notify store owner that customer opened a complaint
            if ($complaint->store?->owner_user_id) {
                $this->send(
                    user: $complaint->store->owner_user_id,
                    eventType: 'COMPLAINT_UPDATED',
                    title: 'Keluhan / Komplain Pelanggan Masuk',
                    message: "Pelanggan {$complaint->customer?->name} mengajukan komplain pada pesanan #{$complaint->order?->order_number}: '{$complaint->description}'.",
                    payload: ['complaint_id' => $complaint->id, 'order_id' => $complaint->order_id],
                    channels: ['in_app', 'whatsapp']
                );
            }
        } else {
            // Notify customer about investigation or resolution
            if ($complaint->customer_id) {
                $statusText = match ($status) {
                    'IN_INVESTIGATION' => 'Sedang Diinvestigasi',
                    'RESOLVED' => 'Telah Diselesaikan',
                    'REJECTED' => 'Ditolak',
                    default => $status,
                };

                $message = "Status komplain pesanan #{$complaint->order?->order_number} diperbarui menjadi '{$statusText}'." .
                    ($resolutionNote ? " Catatan penyelesaian: {$resolutionNote}" : "");

                $this->send(
                    user: $complaint->customer_id,
                    eventType: 'COMPLAINT_UPDATED',
                    title: "Pembaruan Penanganan Komplain ({$statusText})",
                    message: $message,
                    payload: ['complaint_id' => $complaint->id, 'order_id' => $complaint->order_id, 'status' => $status],
                    channels: ['in_app', 'whatsapp', 'email']
                );
            }
        }
    }
}
