<?php

namespace App\Services\WhatsApp;

use App\Models\Notification;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;

class WhatsAppService
{
    /**
     * Normalize a phone number to Indonesian format: 628xxxxxxxxxx
     *
     * Handles:
     * - 0812... -> 62812...
     * - +62 812-... -> 62812...
     * - 62812... -> 62812...
     * - 812... -> 62812...
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        // Remove all non-digit characters
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (empty($digits)) {
            return null;
        }

        if (str_starts_with($digits, '08')) {
            $digits = '628' . substr($digits, 2);
        } elseif (str_starts_with($digits, '628')) {
            // Already standard 628...
        } elseif (str_starts_with($digits, '8')) {
            $digits = '628' . substr($digits, 1);
        } elseif (str_starts_with($digits, '62') && strlen($digits) > 8) {
            // E.g. 62...
        } else {
            return null;
        }

        // Typical Indonesian phone number is between 10 and 16 digits
        if (strlen($digits) < 10 || strlen($digits) > 16) {
            return null;
        }

        return $digits;
    }

    /**
     * Resolve recipient phone number based on role and store rules.
     * Rule: Penerima owner toko: pakai stores.whatsapp, fallback stores.phone, fallback users.phone
     */
    public static function resolveRecipientPhone(User $user, array $data = []): ?string
    {
        $role = $user->role ?? null;
        $isStoreOwner = in_array($role, ['store_admin', 'store_owner'], true)
            || Store::where('owner_user_id', $user->id)->exists();

        if ($isStoreOwner) {
            $store = null;
            if (!empty($data['store_id'])) {
                $store = Store::find($data['store_id']);
            }
            if (!$store) {
                $store = Store::where('owner_user_id', $user->id)->first();
            }

            if ($store) {
                if (!empty($store->whatsapp)) {
                    return $store->whatsapp;
                }
                if (!empty($store->phone)) {
                    return $store->phone;
                }
            }
        }

        return $user->phone;
    }

    /**
     * Build clean plain-text WhatsApp notification without emoji or promotional text.
     * Contains: nomor pesanan, status, nama gerai, keterangan, dan link.
     */
    public static function buildMessage(Notification $notification): string
    {
        $data = $notification->data ?? [];
        $orderId = $data['order_id'] ?? null;
        $order = $orderId ? Order::with('store')->find($orderId) : null;
        $baseUrl = rtrim(config('app.url', 'http://localhost:3000'), '/');

        // Bersihkan keterangan dari URL duplikat di akhir teks bila sudah ada
        $rawContent = $notification->content ?? '';
        $cleanContent = preg_replace('/\s*(Cek pesanan:?\s*http\S+|Silakan proses pesanan di portal toko:?\s*http\S+)/i', '', $rawContent);
        $cleanContent = trim($cleanContent);

        $lines = [];

        if ($order) {
            $lines[] = '*LaundryKu - Update Pesanan*';
            $lines[] = '';
            $lines[] = '*No. Pesanan*: ' . $order->order_number;
            if ($order->store?->name) {
                $lines[] = '*Gerai*: ' . $order->store->name;
            }
            $statusText = $notification->title ?: ($order->status ?? 'Dalam Proses');
            $lines[] = '*Status*: ' . $statusText;
            if ($cleanContent) {
                $lines[] = '*Keterangan*: ' . $cleanContent;
            }
            $lines[] = '';

            // Tentukan link target: mitra toko ke portal toko, pelanggan ke pelacakan pesanan
            $isStoreUser = str_contains($notification->type ?? '', 'store')
                || in_array($notification->user?->role ?? '', ['store_admin', 'store_owner'], true);

            $lines[] = '*Lihat Detail Pesanan:*';
            if ($isStoreUser) {
                $lines[] = $baseUrl . '/store/orders/' . $order->id;
            } else {
                $lines[] = $baseUrl . '/orders/' . $order->id;
            }
        } else {
            $title = $notification->title ?: 'Notifikasi LaundryKu';
            $lines[] = "*LaundryKu - {$title}*";
            $lines[] = '';
            if ($cleanContent) {
                $lines[] = '*Keterangan*: ' . $cleanContent;
            }
            if (!empty($data['store_id'])) {
                $store = Store::find($data['store_id']);
                if ($store?->name) {
                    $lines[] = '*Gerai*: ' . $store->name;
                }
            }
            $lines[] = '';
            $lines[] = '*Buka LaundryKu:*';
            $lines[] = $baseUrl;
        }

        return implode("\n", $lines);
    }
}
