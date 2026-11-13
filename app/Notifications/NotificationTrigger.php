<?php

namespace App\Notifications;

use App\Jobs\SendUserNotificationJob;
use App\Models\Notification;
use App\Models\User;

/**
 * Thin trigger API used by services/controllers.
 *
 * All methods only ENQUEUE a queued job — zero synchronous work, zero
 * exceptions escaping to the caller, zero impact on transaction state.
 */
class NotificationTrigger
{
    public static function toUser(?User $user, string $type, string $title, string $content, array $data = []): void
    {
        if (!$user) {
            return;
        }

        try {
            SendUserNotificationJob::dispatch($user->id, $type, $title, $content, $data);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Notification dispatch failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'type' => $type,
            ]);
        }
    }

    // ===== Registration =====
    public static function registered(User $user): void
    {
        self::toUser($user, 'registration', 'Welcome to LANDURYKU', 'Your account has been created. Explore laundry stores and place your first order!', ['user_id' => $user->id]);
    }

    // ===== Store lifecycle =====
    public static function storeSubmitted(User $owner, $store): void
    {
        self::toUser($owner, 'store_submitted', 'Store application submitted', "Your store \"{$store->name}\" has been submitted and is awaiting review.", ['store_id' => $store->id]);
    }

    public static function storeApproved(User $owner, $store): void
    {
        self::toUser($owner, 'store_approved', 'Store approved', "Congratulations! Your store \"{$store->name}\" has been approved and is now live.", ['store_id' => $store->id]);
    }

    public static function storeRevisionRequested(User $owner, $store, ?string $reason = null): void
    {
        self::toUser($owner, 'store_revision', 'Revision requested for your store', "Your store \"{$store->name}\" needs revision before it can be approved." . ($reason ? " Reason: {$reason}" : ''), ['store_id' => $store->id, 'reason' => $reason]);
    }

    public static function storeRejected(User $owner, $store, string $reason): void
    {
        self::toUser($owner, 'store_rejected', 'Store application rejected', "Your store application \"{$store->name}\" was rejected. Reason: {$reason}", ['store_id' => $store->id, 'reason' => $reason]);
    }

    // ===== Courier flow =====
    public static function courierAssigned(User $courier, $order, string $taskType): void
    {
        self::toUser($courier, 'courier_assigned', "New {$taskType} task assigned", "You have been assigned a {$taskType} task for order #{$order->order_number}.", ['order_id' => $order->id, 'task_type' => $taskType]);
    }

    public static function courierArriving(User $customer, $order): void
    {
        self::toUser($customer, 'courier_arriving', 'Kurir Menuju Lokasi', "Kurir sedang dalam perjalanan menuju lokasi Anda untuk pesanan #{$order->order_number}.", ['order_id' => $order->id]);
    }

    // ===== Order/laundry milestones =====
    public static function pickupCompleted(User $customer, $order): void
    {
        self::toUser($customer, 'pickup_completed', 'Cucian Telah Dijemput', "Cucian untuk pesanan #{$order->order_number} telah berhasil dijemput kurir dan sedang menuju ke gerai.", ['order_id' => $order->id]);
    }

    public static function laundryReceived(User $customer, $order): void
    {
        self::toUser($customer, 'laundry_received', 'Cucian Tiba di Gerai', "Cucian untuk pesanan #{$order->order_number} telah tiba dan diterima oleh pihak gerai.", ['order_id' => $order->id]);
    }

    public static function laundryWeighed(User $customer, $order, $weight = null): void
    {
        self::toUser($customer, 'laundry_weighed', 'Cucian Selesai Ditimbang', "Cucian untuk pesanan #{$order->order_number} telah ditimbang" . ($weight ? " seberat {$weight} kg." : '.') . ' Rincian biaya akan segera diperbarui.', ['order_id' => $order->id, 'weight' => $weight]);
    }

    public static function finalPriceUpdated(User $customer, $order, $oldPrice, $newPrice): void
    {
        self::toUser($customer, 'final_price_updated', 'Harga Final Diperbarui', "Harga final untuk pesanan #{$order->order_number} adalah Rp " . number_format((float) $newPrice, 0, ',', '.') . '.', ['order_id' => $order->id, 'old_price' => $oldPrice, 'new_price' => $newPrice]);
    }

    public static function processing(User $customer, $order): void
    {
        self::toUser($customer, 'processing', 'Cucian Sedang Diproses', "Cucian untuk pesanan #{$order->order_number} sedang dalam proses pencucian & perawatan oleh gerai.", ['order_id' => $order->id]);
    }

    public static function ready(User $customer, $order): void
    {
        self::toUser($customer, 'ready', 'Cucian Siap Diantar', "Cucian untuk pesanan #{$order->order_number} telah selesai diproses dan siap untuk diantar.", ['order_id' => $order->id]);
    }

    public static function deliveryOngoing(User $customer, $order): void
    {
        self::toUser($customer, 'out_for_delivery', 'Cucian Sedang Diantar', "Cucian untuk pesanan #{$order->order_number} sedang dalam perjalanan diantar oleh kurir ke alamat Anda.", ['order_id' => $order->id]);
    }

    public static function completed(User $customer, $order): void
    {
        self::toUser($customer, 'completed', 'Pesanan Selesai', "Pesanan #{$order->order_number} telah selesai. Terima kasih telah mempercayakan cucian Anda di LaundryKu!", ['order_id' => $order->id]);
    }

    // ===== Payment =====
    public static function paymentRequired(User $customer, $order, $amount = null): void
    {
        self::toUser($customer, 'payment_required', 'Menunggu Pembayaran', "Menunggu pembayaran untuk pesanan #{$order->order_number}" . ($amount ? ' (Rp ' . number_format((float) $amount, 0, ',', '.') . ')' : '') . '. Silakan lakukan pembayaran agar pesanan dapat segera diproses.', ['order_id' => $order->id, 'amount' => $amount]);
    }

    public static function paymentSuccess(User $customer, $order): void
    {
        self::toUser($customer, 'payment_success', 'Pembayaran Berhasil', "Pembayaran untuk pesanan #{$order->order_number} telah berhasil diverifikasi.", ['order_id' => $order->id]);
    }

    public static function paymentFailed(User $customer, $order): void
    {
        self::toUser($customer, 'payment_failed', 'Pembayaran Belum Berhasil', "Pembayaran untuk pesanan #{$order->order_number} belum berhasil. Silakan coba metode pembayaran lainnya.", ['order_id' => $order->id]);
    }

    public static function paymentExpired(User $customer, $order): void
    {
        self::toUser($customer, 'payment_expired', 'Pembayaran Kedaluwarsa', "Waktu pembayaran untuk pesanan #{$order->order_number} telah kedaluwarsa. Silakan lakukan pemesanan atau pembayaran ulang.", ['order_id' => $order->id]);
    }

    public static function codConfirmation(User $customer, $order): void
    {
        self::toUser($customer, 'cod_confirmation', 'Konfirmasi Pembayaran COD', "Mohon konfirmasi pembayaran tunai (COD) untuk pesanan #{$order->order_number}.", ['order_id' => $order->id]);
    }

    // ===== Refund =====
    public static function refundRequested(User $customer, $order, $refund): void
    {
        self::toUser($customer, 'refund_requested', 'Refund requested', "A refund of " . number_format((float) $refund->amount, 2) . " has been requested for order #{$order->order_number}.", ['order_id' => $order->id, 'refund_id' => $refund->id]);
    }

    public static function refundCompleted(User $customer, $order, $refund): void
    {
        self::toUser($customer, 'refund_completed', 'Refund completed', "Your refund of " . number_format((float) $refund->amount, 2) . " for order #{$order->order_number} has been completed.", ['order_id' => $order->id, 'refund_id' => $refund->id]);
    }

    // ===== Dispute =====
    public static function disputeOpened(User $recipient, $order, $dispute): void
    {
        self::toUser($recipient, 'dispute_opened', 'Dispute opened', "A dispute was opened for order #{$order->order_number} (case {$dispute->case_number}).", ['order_id' => $order->id, 'dispute_id' => $dispute->id]);
    }

    public static function disputeResolved(User $recipient, $order, $dispute, string $outcome): void
    {
        self::toUser($recipient, 'dispute_' . $outcome, 'Dispute resolved', "Your dispute for order #{$order->order_number} has been resolved (outcome: {$outcome}).", ['order_id' => $order->id, 'dispute_id' => $dispute->id, 'outcome' => $outcome]);
    }

    // ===== Generic order status (refactored legacy listener entry point) =====
    public static function orderStatusChanged(User $customer, $order, string $oldStatus, string $newStatus, ?string $reason = null): void
    {
        if ($oldStatus === 'created' && strtolower($newStatus) === 'created') {
            self::toUser(
                $customer,
                'order_status',
                'Pesanan Berhasil Dibuat',
                "Pesanan Anda #{$order->order_number} telah berhasil dibuat dan sedang menunggu konfirmasi gerai.",
                ['order_id' => $order->id, 'old_status' => $oldStatus, 'new_status' => $newStatus, 'reason' => $reason]
            );
            return;
        }

        $statusLabel = self::statusToIndonesian($newStatus);
        $title = "Status Pesanan: {$statusLabel}";
        $content = "Status pesanan #{$order->order_number} telah diperbarui menjadi {$statusLabel}." . ($reason ? " (Catatan: {$reason})" : '');

        self::toUser($customer, 'order_status', $title, $content, [
            'order_id' => $order->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
        ]);
    }

    public static function statusToIndonesian(string $status): string
    {
        return match (strtolower(trim($status))) {
            'created' => 'Pesanan Dibuat',
            'waiting_pickup' => 'Menunggu Penjemputan',
            'courier_assigned' => 'Kurir Penjemputan Ditugaskan',
            'courier_to_customer' => 'Kurir Menuju Lokasi Anda',
            'picked_up' => 'Cucian Telah Dijemput',
            'in_transit_to_store' => 'Cucian Menuju Gerai',
            'at_store', 'received_by_store' => 'Tiba di Gerai',
            'weighed' => 'Selesai Ditimbang',
            'awaiting_price_approval' => 'Menunggu Persetujuan Harga',
            'price_approved' => 'Harga Disetujui',
            'awaiting_payment', 'payment_required' => 'Menunggu Pembayaran',
            'paid' => 'Sudah Dibayar',
            'processing' => 'Sedang Diproses',
            'ready_for_delivery' => 'Siap Diantar',
            'courier_assigned_for_delivery' => 'Kurir Pengantaran Ditugaskan',
            'out_for_delivery' => 'Sedang Diantar ke Pelanggan',
            'delivered' => 'Tiba di Tujuan',
            'completed' => 'Pesanan Selesai',
            'cancelled' => 'Pesanan Dibatalkan',
            'pickup_failed' => 'Penjemputan Gagal',
            'delivery_failed' => 'Pengantaran Gagal',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }

    // ===== Tahap 11D: Event Triggers & Gap Closures =====

    public static function newOrderForStore($order): void
    {
        if (!$order) {
            return;
        }
        $store = $order->store;
        $owner = $store?->owner ?? ($store?->owner_user_id ? User::find($store->owner_user_id) : null);
        if (!$owner) {
            return;
        }
        $baseUrl = rtrim(config('app.url', 'http://localhost:3000'), '/');
        $customerName = $order->customer?->name ?? 'Pelanggan';
        $serviceName = $order->service_type ?? ($order->items?->first()?->service_name ?? 'Laundry');

        self::toUser(
            $owner,
            'new_order_store',
            "Pesanan Baru Masuk #{$order->order_number}",
            "Pesanan baru #{$order->order_number} dari {$customerName} untuk layanan {$serviceName}. Cek pesanan di portal toko: {$baseUrl}/store/orders/{$order->id}",
            ['order_id' => $order->id, 'store_id' => $order->store_id]
        );
    }

    public static function courierTaskTransitioned($order, string $toStatus): void
    {
        if (!$order) {
            return;
        }
        $customer = $order->customer ?? ($order->customer_id ? User::find($order->customer_id) : null);
        if (!$customer) {
            return;
        }
        $baseUrl = rtrim(config('app.url', 'http://localhost:3000'), '/');

        if ($toStatus === 'picked_up') {
            self::toUser(
                $customer,
                'picked_up',
                "Cucian Telah Dijemput Kurir #{$order->order_number}",
                "Kurir telah menjemput cucian untuk pesanan #{$order->order_number} dan menuju ke toko. Cek pesanan: {$baseUrl}/orders/{$order->id}",
                ['order_id' => $order->id]
            );
        } elseif ($toStatus === 'out_for_delivery') {
            self::toUser(
                $customer,
                'out_for_delivery',
                "Kurir Mengantar Pesanan #{$order->order_number}",
                "Kurir sedang dalam perjalanan mengantarkan cucian untuk pesanan #{$order->order_number} ke alamat Anda. Cek pesanan: {$baseUrl}/orders/{$order->id}",
                ['order_id' => $order->id]
            );
        }
    }

    public static function codPaymentReceived($order): void
    {
        if (!$order) {
            return;
        }
        $customer = $order->customer ?? ($order->customer_id ? User::find($order->customer_id) : null);
        if (!$customer) {
            return;
        }
        $baseUrl = rtrim(config('app.url', 'http://localhost:3000'), '/');
        $amount = number_format((float) ($order->total_amount ?? $order->final_price ?? 0), 0, ',', '.');

        self::toUser(
            $customer,
            'cod_payment_received',
            "Pembayaran COD Diterima #{$order->order_number}",
            "Pembayaran tunai (COD) untuk pesanan #{$order->order_number} sebesar Rp {$amount} telah diterima kurir. Cek pesanan: {$baseUrl}/orders/{$order->id}",
            ['order_id' => $order->id, 'payment_status' => 'paid']
        );
    }

    public static function orderPaidForStore($order): void
    {
        if (!$order) {
            return;
        }
        $store = $order->store;
        $owner = $store?->owner ?? ($store?->owner_user_id ? User::find($store->owner_user_id) : null);
        if (!$owner) {
            return;
        }
        $baseUrl = rtrim(config('app.url', 'http://localhost:3000'), '/');

        self::toUser(
            $owner,
            'order_paid_store',
            "Pembayaran Pesanan Lunas #{$order->order_number}",
            "Pembayaran untuk pesanan #{$order->order_number} telah lunas. Silakan proses pesanan di portal toko: {$baseUrl}/store/orders/{$order->id}",
            ['order_id' => $order->id, 'store_id' => $order->store_id, 'payment_status' => 'paid']
        );
    }

    public static function orderCompletedForStore($order): void
    {
        if (!$order) {
            return;
        }
        $store = $order->store;
        $owner = $store?->owner ?? ($store?->owner_user_id ? User::find($store->owner_user_id) : null);
        if (!$owner) {
            return;
        }
        $baseUrl = rtrim(config('app.url', 'http://localhost:3000'), '/');

        self::toUser(
            $owner,
            'order_completed_store',
            "Pesanan Selesai #{$order->order_number}",
            "Pesanan #{$order->order_number} telah selesai dan diterima oleh pelanggan. Cek detail di portal toko: {$baseUrl}/store/orders/{$order->id}",
            ['order_id' => $order->id, 'store_id' => $order->store_id, 'status' => 'completed']
        );
    }

    public static function orderCancelledForStore($order, ?string $reason = null): void
    {
        if (!$order) {
            return;
        }
        $store = $order->store;
        $owner = $store?->owner ?? ($store?->owner_user_id ? User::find($store->owner_user_id) : null);
        if (!$owner) {
            return;
        }
        $baseUrl = rtrim(config('app.url', 'http://localhost:3000'), '/');

        self::toUser(
            $owner,
            'order_cancelled_store',
            "Pesanan Dibatalkan #{$order->order_number}",
            "Pesanan #{$order->order_number} telah dibatalkan." . ($reason ? " Alasan: {$reason}." : '') . " Cek detail di portal toko: {$baseUrl}/store/orders/{$order->id}",
            ['order_id' => $order->id, 'store_id' => $order->store_id, 'status' => 'cancelled', 'reason' => $reason]
        );
    }

    public static function disputeOpenedForStoreAndAdmins($order, $dispute): void
    {
        if (!$order) {
            return;
        }
        $baseUrl = rtrim(config('app.url', 'http://localhost:3000'), '/');
        $store = $order->store;
        $owner = $store?->owner ?? ($store?->owner_user_id ? User::find($store->owner_user_id) : null);
        if ($owner) {
            self::toUser(
                $owner,
                'dispute_opened_store',
                "Komplain Baru Diajukan #{$order->order_number}",
                "Komplain baru telah diajukan pelanggan untuk pesanan #{$order->order_number}. Cek di portal toko: {$baseUrl}/store/complaints",
                ['order_id' => $order->id, 'dispute_id' => $dispute->id, 'store_id' => $order->store_id]
            );
        }

        try {
            $adminRoles = \App\Models\Role::whereIn('name', ['super_admin', 'platform_owner', 'platform_admin'])->pluck('id');
            $admins = User::whereIn('role', [User::ROLE_SUPER_ADMIN, 'platform_owner', 'platform_admin'])
                ->orWhereHas('roles', fn ($q) => $q->whereIn('roles.id', $adminRoles))
                ->get();

            $caseNum = $dispute->complaint_number ?? $dispute->case_number ?? $dispute->id;
            foreach ($admins as $admin) {
                self::toUser(
                    $admin,
                    'dispute_opened_admin',
                    "Komplain Baru Diajukan #{$order->order_number}",
                    "Komplain baru untuk pesanan #{$order->order_number} (kasus #{$caseNum}) memerlukan pemantauan platform.",
                    ['order_id' => $order->id, 'dispute_id' => $dispute->id]
                );
            }
        } catch (\Throwable $e) {
            // Never break caller
        }
    }

    public static function disputeResolvedForStore($order, $dispute, string $outcome): void
    {
        if (!$order) {
            return;
        }
        $store = $order->store;
        $owner = $store?->owner ?? ($store?->owner_user_id ? User::find($store->owner_user_id) : null);
        if (!$owner) {
            return;
        }
        $baseUrl = rtrim(config('app.url', 'http://localhost:3000'), '/');

        self::toUser(
            $owner,
            'dispute_' . $outcome . '_store',
            "Komplain Diselesaikan #{$order->order_number}",
            "Komplain untuk pesanan #{$order->order_number} telah diselesaikan dengan keputusan: {$outcome}. Cek di portal toko: {$baseUrl}/store/complaints",
            ['order_id' => $order->id, 'dispute_id' => $dispute->id, 'store_id' => $order->store_id, 'outcome' => $outcome]
        );
    }

    public static function readyForDeliveryCourier($order): void
    {
        if (!$order) {
            return;
        }
        $courierUser = $order->courier;
        if (!$courierUser && $order->courier_id) {
            $courierUser = User::find($order->courier_id);
        }
        if (!$courierUser && $order->courier_id) {
            $courierRecord = \App\Models\Courier::find($order->courier_id);
            $courierUser = $courierRecord?->user;
        }
        if (!$courierUser && method_exists($order, 'courierTasks')) {
            $task = $order->courierTasks()->whereIn('task_type', ['delivery', 'pickup'])->latest()->first();
            $courierUser = $task?->courier?->user;
        }

        if (!$courierUser) {
            return;
        }

        $storeName = $order->store?->name ?? 'toko';
        self::toUser(
            $courierUser,
            'order_ready_courier',
            "Pesanan Siap Diantar #{$order->order_number}",
            "Cucian untuk pesanan #{$order->order_number} telah siap diantar. Silakan ambil di {$storeName} dan antar ke pelanggan.",
            ['order_id' => $order->id, 'courier_id' => $courierUser->id]
        );
    }

    public static function courierRecruited($courier, $store): void
    {
        if (!$courier) {
            return;
        }
        $courierUser = $courier->user ?? ($courier->user_id ? User::find($courier->user_id) : null);
        if (!$courierUser) {
            return;
        }
        $storeName = $store?->name ?? 'Toko Mitra';

        self::toUser(
            $courierUser,
            'courier_recruited',
            'Lamaran Kurir Diterima',
            "Selamat! Lamaran Anda telah diterima oleh toko {$storeName}. Anda kini resmi bertugas sebagai kurir toko tersebut.",
            ['store_id' => $store?->id]
        );
    }

    public static function courierRejected($courier, $store, ?string $reason = null): void
    {
        if (!$courier) {
            return;
        }
        $courierUser = $courier->user ?? ($courier->user_id ? User::find($courier->user_id) : null);
        if (!$courierUser) {
            return;
        }
        $storeName = $store?->name ?? 'Toko Mitra';

        self::toUser(
            $courierUser,
            'courier_rejected',
            'Status Lamaran Kurir',
            "Mohon maaf, lamaran Anda ke toko {$storeName} belum dapat diterima saat ini." . ($reason ? " Alasan: {$reason}." : ''),
            ['store_id' => $store?->id, 'reason' => $reason]
        );
    }
}
