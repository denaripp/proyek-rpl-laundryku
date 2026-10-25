<?php

namespace App\Services\Ai;

use App\Enums\OrderStatus;
use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AiOrderStatsService
{
    /**
     * Explicit mapping of all OrderStatus enum cases to business status groups.
     * All 19 cases of OrderStatus are mapped: none are left unmapped.
     */
    public const STATUS_GROUPS = [
        'selesai' => [
            OrderStatus::COMPLETED->value, // 'completed'
        ],
        'dibatalkan' => [
            OrderStatus::CANCELLED->value, // 'cancelled'
            OrderStatus::PICKUP_FAILED->value, // 'pickup_failed'
            OrderStatus::DELIVERY_FAILED->value, // 'delivery_failed'
        ],
        'diproses' => [
            OrderStatus::CREATED->value, // 'created'
            OrderStatus::WAITING_PICKUP->value, // 'waiting_pickup'
            OrderStatus::COURIER_ASSIGNED->value, // 'courier_assigned'
            OrderStatus::COURIER_TO_CUSTOMER->value, // 'courier_to_customer'
            OrderStatus::PICKED_UP->value, // 'picked_up'
            OrderStatus::IN_TRANSIT_TO_STORE->value, // 'in_transit_to_store'
            OrderStatus::RECEIVED_BY_STORE->value, // 'received_by_store'
            OrderStatus::WEIGHED->value, // 'weighed'
            OrderStatus::PAYMENT_REQUIRED->value, // 'payment_required'
            OrderStatus::PAID->value, // 'paid'
            OrderStatus::PROCESSING->value, // 'processing'
            OrderStatus::READY_FOR_DELIVERY->value, // 'ready_for_delivery'
            OrderStatus::COURIER_ASSIGNED_FOR_DELIVERY->value, // 'courier_assigned_for_delivery'
            OrderStatus::OUT_FOR_DELIVERY->value, // 'out_for_delivery'
            OrderStatus::DELIVERED->value, // 'delivered'
        ],
    ];

    protected array $statusLabels = [
        'created' => 'Menunggu Penjemputan',
        'waiting_pickup' => 'Menunggu Penjemputan Kurir',
        'courier_assigned' => 'Kurir Ditugaskan',
        'courier_to_customer' => 'Kurir Menuju Lokasi Anda',
        'picked_up' => 'Pakaian Dijemput Kurir',
        'in_transit_to_store' => 'Dalam Perjalanan ke Outlet Toko',
        'received_by_store' => 'Diterima Outlet Toko',
        'weighed' => 'Selesai Ditimbang',
        'payment_required' => 'Menunggu Pembayaran',
        'paid' => 'Sudah Dibayar',
        'processing' => 'Sedang Diproses',
        'ready_for_delivery' => 'Siap Diantar',
        'courier_assigned_for_delivery' => 'Kurir Pengantaran Ditugaskan',
        'out_for_delivery' => 'Sedang Diantar Kurir',
        'delivered' => 'Terkirim',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
        'pickup_failed' => 'Penjemputan Gagal',
        'delivery_failed' => 'Pengantaran Gagal',
    ];

    /**
     * Generate deterministic response for order statistics based on user role and query.
     */
    public function getStatsResponse(?User $user, string $message): string
    {
        if (!$user) {
            return 'Untuk melihat statistik pesanan, silakan masuk ke akun Anda terlebih dahulu melalui menu Masuk / Daftar (/auth).';
        }

        $roles = $this->getUserRoles($user);
        $normalized = mb_strtolower(trim($message));

        $isStoreManager = in_array('store_owner', $roles, true) || in_array('store_admin', $roles, true);
        $isCourier = in_array('courier', $roles, true);
        $isPlatformAdmin = in_array('super_admin', $roles, true) || in_array('platform_owner', $roles, true);

        // 1. Courier specific query: "tugas kurir saya", "tugas pengantaran", etc.
        if ((str_contains($normalized, 'tugas') || str_contains($normalized, 'kurir'))
            && !str_contains($normalized, 'memesan')
            && !str_contains($normalized, 'belanja')
            && !str_contains($normalized, 'pengeluaran')) {
            if ($isCourier) {
                return $this->getCourierStats($user);
            }
        }

        // 2. Super Admin / Platform wide query
        if ($isPlatformAdmin
            && (str_contains($normalized, 'platform') || str_contains($normalized, 'keseluruhan') || str_contains($normalized, 'seluruh') || str_contains($normalized, 'semua pesanan'))) {
            return $this->getPlatformStats();
        }

        // 3. Store specific query (e.g. "berapa pesanan masuk toko saya", "pesanan toko hari ini", "pesanan masuk", "pesanan toko", "statistik toko")
        $isStoreInquiry = str_contains($normalized, 'toko')
            || str_contains($normalized, 'pesanan masuk')
            || str_contains($normalized, 'masuk toko')
            || str_contains($normalized, 'outlet')
            || str_contains($normalized, 'kelola')
            || str_contains($normalized, 'orderan masuk')
            || str_contains($normalized, 'orderan toko');

        // 4. Personal customer ordering inquiry (e.g. "berapa kali saya memesan", "total pengeluaran saya", "pesanan terakhir saya", "belanja saya")
        $isCustomerOrderingInquiry = str_contains($normalized, 'saya memesan')
            || str_contains($normalized, 'memesan')
            || str_contains($normalized, 'pengeluaran')
            || str_contains($normalized, 'belanja')
            || str_contains($normalized, 'terakhir')
            || str_contains($normalized, 'cucian saya')
            || str_contains($normalized, 'cucian')
            || str_contains($normalized, 'uang')
            || str_contains($normalized, 'duit')
            || str_contains($normalized, 'habis')
            || str_contains($normalized, 'keluar')
            || str_contains($normalized, 'beli');

        if ($isStoreInquiry && !$isCustomerOrderingInquiry) {
            if ($isStoreManager) {
                return $this->getStoreStats($user);
            }
        }

        if ($isCustomerOrderingInquiry && !$isStoreInquiry) {
            return $this->getCustomerStats($user, $normalized);
        }

        // 5. Handling for Store Managers when query is ambiguous (e.g. "berapa pesanan saya", "pesanan saya berapa", "jumlah pesanan saya")
        if ($isStoreManager) {
            // If the query specifically has "memesan", user explicitly asked about their customer orders
            if (str_contains($normalized, 'memesan')) {
                return $this->getCustomerStats($user, $normalized);
            }

            // Ambiguous query: mention both customer and store stats concisely
            return $this->getAmbiguousStoreManagerStats($user, $normalized);
        }

        // Default: Customer order statistics
        return $this->getCustomerStats($user, $normalized);
    }

    /**
     * Ambiguous query response for store managers.
     * Mentions both personal customer orders and store orders concisely.
     */
    public function getAmbiguousStoreManagerStats(User $user, string $normalizedMessage): string
    {
        $customerStats = $this->getCustomerStats($user, $normalizedMessage);
        $storeStats = $this->getStoreStats($user);

        return "Sebagai pelanggan: {$customerStats}\n{$storeStats}";
    }

    /**
     * Statistics for Customer.
     */
    public function getCustomerStats(User $user, string $normalizedMessage): string
    {
        // 1. Specific query: "pesanan terakhir"
        if (str_contains($normalizedMessage, 'terakhir')) {
            $lastOrder = Order::where('customer_id', $user->id)
                ->latest('order_date')
                ->latest('id')
                ->first();

            if (!$lastOrder) {
                return 'Anda belum memiliki pesanan di LaundryKu.';
            }

            $dateWib = $this->formatWibDate($lastOrder->order_date ?? $lastOrder->created_at);
            $label = $this->statusLabels[$lastOrder->status] ?? $lastOrder->status;

            return "Pesanan terakhir Anda nomor {$lastOrder->order_number} dengan status '{$label}' pada tanggal {$dateWib}.";
        }

        // 2. Specific query: "total pengeluaran" / "belanja"
        if (str_contains($normalizedMessage, 'pengeluaran') || str_contains($normalizedMessage, 'belanja') || str_contains($normalizedMessage, 'uang') || str_contains($normalizedMessage, 'duit') || str_contains($normalizedMessage, 'keluar') || str_contains($normalizedMessage, 'habis')) {
            $spending = $this->calculateCustomerSpending($user->id);

            return 'Total pengeluaran Anda untuk pesanan yang telah selesai dan dibayar adalah Rp ' . number_format($spending, 0, ',', '.') . '.';
        }

        // 3. Specific query: "pesanan selesai saya berapa" / "berapa pesanan selesai"
        if ((str_contains($normalizedMessage, 'selesai') || str_contains($normalizedMessage, 'kelar'))
            && !str_contains($normalizedMessage, 'proses')
            && !str_contains($normalizedMessage, 'diproses')) {
            $completedCount = Order::where('customer_id', $user->id)
                ->whereIn('status', self::STATUS_GROUPS['selesai'])
                ->count();

            if ($completedCount === 0) {
                return 'Belum ada pesanan Anda yang selesai.';
            }

            return "Anda memiliki {$completedCount} pesanan yang telah selesai.";
        }

        // 4. Specific query: "pesanan yang sedang diproses"
        if ((str_contains($normalizedMessage, 'proses') || str_contains($normalizedMessage, 'diproses') || str_contains($normalizedMessage, 'cuci'))
            && !str_contains($normalizedMessage, 'selesai')
            && !str_contains($normalizedMessage, 'kelar')) {
            $processingCount = Order::where('customer_id', $user->id)
                ->whereIn('status', self::STATUS_GROUPS['diproses'])
                ->count();

            if ($processingCount === 0) {
                return 'Saat ini tidak ada pesanan Anda yang sedang diproses.';
            }

            return "Saat ini Anda memiliki {$processingCount} pesanan yang sedang diproses.";
        }

        // 5. General count: "berapa kali saya memesan", "jumlah pesanan saya", dll.
        $query = Order::where('customer_id', $user->id);
        $total = (clone $query)->count();

        if ($total === 0) {
            return 'Anda belum memiliki pesanan di LaundryKu.';
        }

        $selesai = (clone $query)->whereIn('status', self::STATUS_GROUPS['selesai'])->count();
        $diproses = (clone $query)->whereIn('status', self::STATUS_GROUPS['diproses'])->count();
        $dibatalkan = (clone $query)->whereIn('status', self::STATUS_GROUPS['dibatalkan'])->count();

        return "Anda sudah memesan {$total} kali. {$selesai} selesai, {$diproses} diproses, {$dibatalkan} dibatalkan.";
    }

    /**
     * Calculate total spending for a customer:
     * Formula: Sum of COALESCE(total_amount, total_price, 0)
     * Filter:
     * - customer_id = $userId
     * - status = 'completed' (OrderStatus::COMPLETED)
     * - payment_status = 'paid'
     * - Excludes cancelled orders (status must be 'completed')
     * - Excludes orders with refund status in ['requested', 'processing', 'completed']
     */
    public function calculateCustomerSpending(int $userId): float
    {
        return (float) Order::where('customer_id', $userId)
            ->where('status', OrderStatus::COMPLETED->value)
            ->where('payment_status', 'paid')
            ->whereDoesntHave('refund', function ($q) {
                $q->whereIn('status', ['requested', 'processing', 'completed']);
            })
            ->sum(DB::raw('COALESCE(total_amount, total_price, 0)'));
    }

    /**
     * Statistics for Store Owner / Admin.
     */
    public function getStoreStats(User $user): string
    {
        $storeIds = [];
        if (method_exists($user, 'ownedStores')) {
            try {
                $storeIds = array_merge($storeIds, $user->ownedStores()->pluck('id')->all());
            } catch (\Throwable $e) {
            }
        }
        if (method_exists($user, 'storeStaff')) {
            try {
                $staffIds = $user->storeStaff()->where('is_active', true)->pluck('store_id')->all();
                $storeIds = array_merge($storeIds, $staffIds);
            } catch (\Throwable $e) {
            }
        }

        $storeIds = array_values(array_unique(array_filter($storeIds)));

        if (empty($storeIds)) {
            return 'Anda belum memiliki toko mitra yang dikelola.';
        }

        $storeNames = Store::whereIn('id', $storeIds)->pluck('name')->implode(', ');

        $query = Order::whereIn('store_id', $storeIds);
        $total = (clone $query)->count();

        if ($total === 0) {
            return "Toko yang Anda kelola ({$storeNames}) belum menerima pesanan.";
        }

        $selesai = (clone $query)->whereIn('status', self::STATUS_GROUPS['selesai'])->count();
        $diproses = (clone $query)->whereIn('status', self::STATUS_GROUPS['diproses'])->count();
        $dibatalkan = (clone $query)->whereIn('status', self::STATUS_GROUPS['dibatalkan'])->count();

        // Orders placed today strictly in Asia/Jakarta (WIB)
        // Converted properly to storage timezone (config('app.timezone'), e.g. UTC)
        // Uses order_date as the primary order timestamp (fallback to created_at if null)
        $storageTimezone = config('app.timezone', 'UTC');
        $startOfTodayStorage = Carbon::now('Asia/Jakarta')->startOfDay()->setTimezone($storageTimezone);
        $endOfTodayStorage = Carbon::now('Asia/Jakarta')->endOfDay()->setTimezone($storageTimezone);

        $todayCount = (clone $query)
            ->whereRaw('COALESCE(order_date, created_at) >= ? AND COALESCE(order_date, created_at) <= ?', [
                $startOfTodayStorage->format('Y-m-d H:i:s'),
                $endOfTodayStorage->format('Y-m-d H:i:s'),
            ])
            ->count();

        $orderIds = (clone $query)->pluck('id');
        $hasPayments = Payment::whereIn('order_id', $orderIds)->where('status', 'paid')->exists();

        if ($hasPayments) {
            $todayRevenue = (float) Payment::where('status', 'paid')
                ->whereIn('order_id', $orderIds)
                ->whereDate('paid_at', Carbon::today('Asia/Jakarta'))
                ->sum('amount');
            $totalRevenue = (float) Payment::where('status', 'paid')
                ->whereIn('order_id', $orderIds)
                ->sum('amount');
        } else {
            $paidQuery = (clone $query)->where('payment_status', 'paid');
            $totalRevenue = (float) (clone $paidQuery)->sum(DB::raw('COALESCE(total_amount, total_price, 0)'));
            $todayRevenue = (float) (clone $paidQuery)
                ->where(function ($q) use ($startOfTodayStorage, $endOfTodayStorage) {
                    $q->whereBetween('paid_at', [$startOfTodayStorage, $endOfTodayStorage])
                      ->orWhere(function ($sub) use ($startOfTodayStorage, $endOfTodayStorage) {
                          $sub->whereNull('paid_at')
                              ->whereBetween('created_at', [$startOfTodayStorage, $endOfTodayStorage]);
                      });
                })
                ->sum(DB::raw('COALESCE(total_amount, total_price, 0)'));
        }

        $todayFmt = 'Rp ' . number_format($todayRevenue, 0, ',', '.');
        $totalFmt = 'Rp ' . number_format($totalRevenue, 0, ',', '.');

        return "Toko yang Anda kelola ({$storeNames}) menerima {$total} pesanan. {$selesai} selesai, {$diproses} diproses, {$dibatalkan} dibatalkan. Hari ini ada {$todayCount} pesanan masuk. Omzet hari ini: {$todayFmt}. Total omzet: {$totalFmt}.";
    }

    public function getStoreManagerStats(User $user): string
    {
        return $this->getStoreStats($user);
    }

    /**
     * Statistics for Courier.
     * Strictly verifies User -> Courier -> CourierTask relation without assuming courier_id = user_id.
     */
    public function getCourierStats(User $user): string
    {
        $courier = null;
        if (method_exists($user, 'courier')) {
            try {
                $courier = $user->courier;
            } catch (\Throwable $e) {
            }
        }
        if (!$courier) {
            $courier = Courier::where('user_id', $user->id)->first();
        }

        if (!$courier) {
            return 'Anda terdaftar sebagai kurir, namun profil kurir Anda belum memiliki tugas.';
        }

        $taskQuery = CourierTask::where('courier_id', $courier->id);
        $totalTasks = (clone $taskQuery)->count();

        if ($totalTasks === 0) {
            return 'Anda belum memiliki tugas penjemputan atau pengantaran yang ditugaskan.';
        }

        $pickupTasks = (clone $taskQuery)->where('task_type', 'pickup')->count();
        $deliveryTasks = (clone $taskQuery)->where('task_type', 'delivery')->count();

        $completedTasks = (clone $taskQuery)->where('status', 'delivered')->count();
        $activeTasks = (clone $taskQuery)->whereIn('status', ['assigned', 'accepted', 'arrived', 'picked_up'])->count();
        $failedTasks = (clone $taskQuery)->whereIn('status', ['failed', 'cancelled'])->count();

        return "Anda memiliki total {$totalTasks} tugas kurir ({$pickupTasks} penjemputan, {$deliveryTasks} pengantaran). {$completedTasks} selesai, {$activeTasks} sedang berjalan, {$failedTasks} dibatalkan/gagal.";
    }

    /**
     * Statistics for Super Admin / Platform Owner.
     */
    public function getPlatformStats(): string
    {
        $query = Order::query();
        $total = (clone $query)->count();

        if ($total === 0) {
            return 'Belum ada pesanan yang masuk di seluruh platform LaundryKu.';
        }

        $selesai = (clone $query)->whereIn('status', self::STATUS_GROUPS['selesai'])->count();
        $diproses = (clone $query)->whereIn('status', self::STATUS_GROUPS['diproses'])->count();
        $dibatalkan = (clone $query)->whereIn('status', self::STATUS_GROUPS['dibatalkan'])->count();

        return "Ringkasan platform LaundryKu: total {$total} pesanan ({$total} transaksi). {$selesai} selesai, {$diproses} diproses, {$dibatalkan} dibatalkan.";
    }

    /**
     * Format timestamp to Indonesian formatted string in Asia/Jakarta (WIB).
     * Explicitly assumes input is stored in the application storage timezone (config('app.timezone', 'UTC'))
     * and converts it accurately to Asia/Jakarta (WIB).
     */
    public function formatWibDate($dateTime): string
    {
        if (!$dateTime) {
            return '';
        }

        if ($dateTime instanceof Carbon) {
            $dt = $dateTime->copy()->timezone('Asia/Jakarta');
        } else {
            $storageTz = config('app.timezone', 'UTC');
            $dt = Carbon::parse($dateTime, $storageTz)->timezone('Asia/Jakarta');
        }

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $dt->format('d') . ' ' . $months[(int) $dt->format('n')] . ' ' . $dt->format('Y, H:i') . ' WIB';
    }

    protected function getUserRoles(User $user): array
    {
        $roles = [];
        if (method_exists($user, 'roles')) {
            try {
                $roles = $user->roles()->pluck('roles.name')->all();
            } catch (\Throwable $e) {
            }
        }
        if (!empty($user->role) && !in_array($user->role, $roles, true)) {
            $roles[] = $user->role;
        }

        return array_values(array_unique(array_filter($roles)));
    }
}
