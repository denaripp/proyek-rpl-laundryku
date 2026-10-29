<?php

namespace App\Services\Store;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreStaff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StoreDashboardService
{
    public const STATUS_LABELS = [
        'created' => 'Menunggu Penjemputan',
        'pending_pickup' => 'Menunggu Penjemputan',
        'waiting_pickup' => 'Menunggu Penjemputan',
        'courier_assigned' => 'Kurir Ditugaskan (Jemput)',
        'courier_to_customer' => 'Kurir Menuju Lokasi Pelanggan',
        'pickup_assigned' => 'Kurir Ditugaskan (Jemput)',
        'picked_up' => 'Cucian Dijemput',
        'in_transit_to_store' => 'Dalam Perjalanan ke Toko',
        'received_by_store' => 'Diterima di Outlet Toko',
        'at_store' => 'Tiba di Gerai',
        'received' => 'Tiba di Gerai',
        'weighed' => 'Selesai Ditimbang',
        'awaiting_price_approval' => 'Menunggu Konfirmasi Harga',
        'price_approved' => 'Harga Disetujui',
        'payment_required' => 'Menunggu Pembayaran',
        'awaiting_payment' => 'Menunggu Pembayaran',
        'paid' => 'Sudah Dibayar',
        'processing' => 'Sedang Diproses',
        'ready_for_delivery' => 'Siap Diantar',
        'delivery_assigned' => 'Kurir Ditugaskan (Antar)',
        'courier_assigned_for_delivery' => 'Kurir Pengantaran Ditugaskan',
        'out_for_delivery' => 'Sedang Diantar',
        'delivered' => 'Cucian Sampai di Pelanggan',
        'completed' => 'Pesanan Selesai',
        'cancelled' => 'Dibatalkan',
        'pickup_failed' => 'Penjemputan Gagal',
        'delivery_failed' => 'Pengantaran Gagal',
    ];

    public const MENU_ITEMS = [
        [
            'title' => 'Dashboard',
            'path' => '/store',
            'desc' => 'Ringkasan operasional harian, 5 kartu metrik utama, dan daftar transaksi terbaru.',
        ],
        [
            'title' => 'Pesanan',
            'path' => '/store/orders',
            'desc' => 'Kelola antrean pesanan masuk, penimbangan berat fisik pakaian, pemrosesan cucian, dan penugasan (assign) kurir.',
        ],
        [
            'title' => 'Layanan',
            'path' => '/store/services',
            'desc' => 'Tambah layanan baru gerai, kelola nama, deskripsi, dan status aktif/nonaktif layanan.',
        ],
        [
            'title' => 'Harga',
            'path' => '/store/pricing',
            'desc' => 'Atur tarif per unit/kiloan serta ketentuan minimum charge tiap layanan.',
        ],
        [
            'title' => 'Kurir',
            'path' => '/store/couriers',
            'desc' => 'Daftar kurir terikat toko, pemantauan kurir aktif/siap tugas, dan rekrutmen pelamar kurir terverifikasi.',
        ],
        [
            'title' => 'Laporan',
            'path' => '/store/reports',
            'desc' => 'Analitik pendapatan harian/bulanan, rekapitulasi penjualan, dan laporan performa operasional.',
        ],
        [
            'title' => 'Pengaturan',
            'path' => '/store/settings',
            'desc' => 'Pengaturan jadwal jam operasional, radius jangkauan layanan (km), lokasi/alamat gerai, dan status buka/tutup toko.',
        ],
        [
            'title' => 'Profil Gerai',
            'path' => '/store/profile',
            'desc' => 'Informasi profil toko, kontak telepon, dan data kepemilikan akun mitra.',
        ],
        [
            'title' => 'Ulasan & Rating',
            'path' => '/store/ratings',
            'desc' => 'Melihat ulasan dan rating kepuasan yang diberikan oleh pelanggan.',
        ],
        [
            'title' => 'Komplain',
            'path' => '/store/complaints',
            'desc' => 'Pusat penanganan komplain dan dispute pesanan dari pelanggan.',
        ],
        [
            'title' => 'Buku Kas',
            'path' => '/store/ledger',
            'desc' => 'Pencatatan mutasi keuangan gerai, bagi hasil platform, dan upah kurir.',
        ],
    ];

    /**
     * Resolve store for authenticated mitra (covers store owner AND active store staff).
     */
    public function resolveStore(User $user, ?string $storeId = null): ?Store
    {
        if ($storeId) {
            $store = Store::where('id', $storeId)
                ->where(function ($q) use ($user) {
                    $q->where('owner_user_id', $user->id)
                      ->orWhereHas('staff', fn ($s) => $s->where('user_id', $user->id)->where('is_active', true));
                })
                ->first();

            if ($store) {
                return $store;
            }
        }

        // 1. Check if user is the store owner
        $owned = Store::where('owner_user_id', $user->id)->first();
        if ($owned) {
            return $owned;
        }

        // 2. Check if user is active staff of a store
        $staff = StoreStaff::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('store')
            ->first();

        return $staff?->store;
    }

    /**
     * Format store display name with "Toko " prefix.
     */
    public function getStoreDisplayName(?Store $store): string
    {
        $name = trim($store->name ?? 'Toko Anda');
        return preg_match('/^toko\b/i', $name) ? $name : "Toko {$name}";
    }

    /**
     * Format timestamp with Asia/Jakarta timezone.
     */
    public function formatTimestamp(?string $raw, $fallback = null): ?string
    {
        if ($raw) {
            return Carbon::parse($raw, 'Asia/Jakarta')->toIso8601String();
        }
        return $fallback ? Carbon::parse($fallback)->setTimezone('Asia/Jakarta')->toIso8601String() : null;
    }

    /**
     * Format number as Indonesian Rupiah currency.
     */
    public function formatRupiah(float|int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    /**
     * Get exact dashboard metrics and recent orders identical to frontend store dashboard.
     */
    public function getDashboardData(Store $store): array
    {
        $orderStats = Order::where('store_id', $store->id)
            ->selectRaw("
                COUNT(CASE WHEN status IN ('created', 'pending_pickup') THEN 1 END) as new_orders,
                COUNT(CASE WHEN status IN ('at_store', 'received', 'weighed', 'awaiting_price_approval', 'price_approved', 'awaiting_payment', 'paid', 'processing') THEN 1 END) as processing_orders,
                COUNT(CASE WHEN status IN ('ready_for_delivery', 'delivery_assigned', 'out_for_delivery') THEN 1 END) as ready_for_delivery,
                COUNT(CASE WHEN status = 'ready_for_delivery' THEN 1 END) as ready_for_delivery_only,
                COUNT(CASE WHEN status IN ('delivery_assigned', 'out_for_delivery') THEN 1 END) as out_for_delivery_only,
                COUNT(CASE WHEN status = 'completed' AND DATE(completed_at) = CURDATE() THEN 1 END) as completed_today,
                COALESCE(SUM(CASE WHEN status = 'completed' AND DATE(completed_at) = CURDATE() THEN total_amount ELSE 0 END), 0) as today_revenue
            ")
            ->first();

        $courierStats = Courier::where('store_id', $store->id)
            ->selectRaw("
                COUNT(*) as total_couriers,
                COUNT(CASE WHEN is_available = 1 THEN 1 END) as active_couriers
            ")
            ->first();

        $newOrders = (int) ($orderStats->new_orders ?? 0);
        $processingOrders = (int) ($orderStats->processing_orders ?? 0);
        $readyForDelivery = (int) ($orderStats->ready_for_delivery ?? 0);
        $readyOnly = (int) ($orderStats->ready_for_delivery_only ?? 0);
        $outOnly = (int) ($orderStats->out_for_delivery_only ?? 0);
        $completedToday = (int) ($orderStats->completed_today ?? 0);
        $todayRevenue = (float) ($orderStats->today_revenue ?? 0);
        $totalCouriers = (int) ($courierStats->total_couriers ?? 0);
        $activeCouriers = (int) ($courierStats->active_couriers ?? 0);

        $recentOrders = Order::where('store_id', $store->id)
            ->with('customer:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($order) {
                $rawCreated = $order->getRawOriginal('created_at');
                return [
                    'id' => (string) $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer?->name ?? 'Pelanggan',
                    'status' => strtoupper($order->status),
                    'status_label' => self::STATUS_LABELS[strtolower($order->status)] ?? strtoupper($order->status),
                    'total_amount' => (float) ($order->total_amount ?? $order->final_price ?? $order->estimated_price ?? 0),
                    'created_at' => $this->formatTimestamp($rawCreated, $order->created_at),
                ];
            });

        return [
            'store' => [
                'id' => (string) $store->id,
                'name' => $store->name,
                'approval_status' => $store->status,
                'operational_status' => $store->is_active ? 'active' : 'inactive',
                'rejection_reason' => $store->rejection_reason ?? null,
            ],
            'metrics' => [
                'new_orders' => $newOrders,
                'processing_orders' => $processingOrders,
                'ready_for_delivery' => $readyForDelivery,
                'ready_for_delivery_only' => $readyOnly,
                'out_for_delivery_only' => $outOnly,
                'completed_today' => $completedToday,
                'today_revenue' => $todayRevenue,
                'active_couriers' => $activeCouriers,
                'total_couriers' => $totalCouriers,
            ],
            'recent_orders' => $recentOrders,
        ];
    }

    /**
     * Chatbot Response: Full Dashboard Summary / Rekap / Kondisi Toko.
     */
    public function getSummaryResponse(Store $store): string
    {
        $data = $this->getDashboardData($store);
        $m = $data['metrics'];
        $storeName = $this->getStoreDisplayName($store);
        $revFmt = $this->formatRupiah($m['today_revenue']);

        return "Ringkasan operasional {$storeName} hari ini:\n"
            . "- Pesanan Baru: {$m['new_orders']} pesanan (menunggu konfirmasi)\n"
            . "- Sedang Diproses: {$m['processing_orders']} pesanan (cuci & setrika)\n"
            . "- Siap Diantar: {$m['ready_for_delivery']} pesanan\n"
            . "- Selesai Hari Ini: {$m['completed_today']} pesanan\n"
            . "- Omset Hari Ini: {$revFmt}\n"
            . "- Kurir Aktif: {$m['active_couriers']} aktif dari total {$m['total_couriers']} kurir terikat.";
    }

    /**
     * Chatbot Response: Metric Pesanan Baru / Masuk.
     */
    public function getNewOrdersResponse(Store $store): string
    {
        $data = $this->getDashboardData($store);
        $count = $data['metrics']['new_orders'];
        $storeName = $this->getStoreDisplayName($store);

        return "{$storeName} memiliki {$count} pesanan baru yang masuk (menunggu konfirmasi & penjemputan).";
    }

    /**
     * Chatbot Response: Metric Sedang Diproses.
     */
    public function getProcessingOrdersResponse(Store $store): string
    {
        $data = $this->getDashboardData($store);
        $count = $data['metrics']['processing_orders'];
        $storeName = $this->getStoreDisplayName($store);

        return "{$storeName} sedang memproses {$count} pesanan cucian (tahap pencucian, pengeringan, & penyetrikaan).";
    }

    /**
     * Chatbot Response: Metric Siap Diantar.
     */
    public function getReadyForDeliveryResponse(Store $store): string
    {
        $data = $this->getDashboardData($store);
        $count = $data['metrics']['ready_for_delivery'];
        $storeName = $this->getStoreDisplayName($store);

        return "{$storeName} memiliki {$count} pesanan yang siap diantar ke pelanggan (menunggu kurir / siap ambil).";
    }

    /**
     * Chatbot Response: Metric Sedang Diantar / Lagi Diantar.
     */
    public function getOutForDeliveryResponse(Store $store): string
    {
        $data = $this->getDashboardData($store);
        $count = $data['metrics']['out_for_delivery_only'];
        $storeName = $this->getStoreDisplayName($store);

        return "{$storeName} memiliki {$count} pesanan yang saat ini sedang dalam perjalanan diantar oleh kurir.";
    }

    /**
     * Chatbot Response: Metric Selesai Hari Ini.
     */
    public function getCompletedTodayResponse(Store $store): string
    {
        $data = $this->getDashboardData($store);
        $count = $data['metrics']['completed_today'];
        $storeName = $this->getStoreDisplayName($store);

        return "Hari ini ada {$count} pesanan yang telah selesai di {$storeName}.";
    }

    /**
     * Chatbot Response: Metric Omset Hari Ini & Omset Terbayar.
     */
    public function getTodayRevenueResponse(Store $store): string
    {
        $data = $this->getDashboardData($store);
        $rev = $data['metrics']['today_revenue'];
        $storeName = $this->getStoreDisplayName($store);
        $fmt = $this->formatRupiah($rev);

        $totalOrders = Order::where('store_id', $store->id)->count();
        if ($totalOrders === 0) {
            return "{$storeName} belum mencatatkan transaksi terbayar hari ini. Saran tindakan: Anda dapat mempromosikan layanan toko atau memastikan jam operasional toko aktif di /store/settings.";
        }

        $totalPaidRevenue = (float) Order::where('store_id', $store->id)
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                  ->orWhere('status', 'completed');
            })
            ->sum(DB::raw('COALESCE(total_amount, total_price, 0)'));
        $totalPaidFmt = $this->formatRupiah($totalPaidRevenue);

        if ($rev == 0 && $totalPaidRevenue == 0) {
            return "{$storeName} belum mencatatkan transaksi terbayar hari ini. Saran tindakan: Anda dapat mempromosikan layanan toko atau memastikan jam operasional toko aktif di /store/settings.";
        }

        return "Omset hari ini di {$storeName} tercatat sebesar {$fmt} dari total transaksi terbayar (Total omzet keseluruhan: {$totalPaidFmt}).";
    }

    /**
     * Chatbot Response: Metric Kurir Aktif & Total Kurir.
     */
    public function getActiveCouriersResponse(Store $store): string
    {
        $data = $this->getDashboardData($store);
        $active = $data['metrics']['active_couriers'];
        $total = $data['metrics']['total_couriers'];
        $storeName = $this->getStoreDisplayName($store);
        $inactive = $total - $active;

        return "{$storeName} memiliki total {$total} kurir terikat ({$active} kurir aktif / siap tugas, {$inactive} tidak aktif).";
    }

    /**
     * Chatbot Response: Tabel Daftar Pesanan Masuk / Terbaru.
     */
    public function getRecentOrdersTableResponse(Store $store): string
    {
        $data = $this->getDashboardData($store);
        $recent = $data['recent_orders'];
        $storeName = $this->getStoreDisplayName($store);

        if ($recent->isEmpty()) {
            return "{$storeName} belum menerima pesanan terbaru hari ini.";
        }

        $lines = ["Daftar pesanan terbaru di {$storeName}:"];
        foreach ($recent as $ord) {
            $totalFmt = $this->formatRupiah($ord['total_amount']);
            $lines[] = "- {$ord['order_number']} | Pelanggan: {$ord['customer_name']} | Status: {$ord['status_label']} | Total: {$totalFmt}";
        }
        $lines[] = "Detail lengkap dapat dikelola di menu Pesanan (/store/orders).";

        return implode("\n", $lines);
    }

    /**
     * Chatbot Response: Pesanan Yang Paling Lama Menunggu.
     */
    public function getOldestWaitingOrderResponse(Store $store): string
    {
        $storeName = $this->getStoreDisplayName($store);

        $oldest = Order::where('store_id', $store->id)
            ->whereIn('status', ['created', 'pending_pickup'])
            ->with('customer:id,name')
            ->oldest()
            ->first();

        if (!$oldest) {
            return "Saat ini tidak ada antrean pesanan baru yang menunggu konfirmasi di {$storeName}.";
        }

        $custName = $oldest->customer?->name ?? 'Pelanggan';
        $waktu = Carbon::parse($oldest->created_at)->setTimezone('Asia/Jakarta')->diffForHumans();
        $totalFmt = $this->formatRupiah((float) ($oldest->total_amount ?? $oldest->final_price ?? 0));

        return "Pesanan yang paling lama menunggu di {$storeName} adalah {$oldest->order_number} (Pelanggan: {$custName}, Total: {$totalFmt}, Masuk sejak: {$waktu}). Segera proses di /store/orders.";
    }

    /**
     * Chatbot Response: Status Spesifik ORD-xxxx.
     */
    public function getOrderByNumberResponse(Store $store, string $orderNumber): string
    {
        $storeName = $this->getStoreDisplayName($store);

        $order = Order::where('order_number', $orderNumber)->first();
        if (!$order || (int) $order->store_id !== (int) $store->id) {
            return "Pesanan dengan nomor {$orderNumber} tidak ditemukan pada toko yang Anda kelola.";
        }

        $statusLabel = self::STATUS_LABELS[strtolower($order->status)] ?? strtoupper($order->status);
        $totalFmt = $this->formatRupiah((float) ($order->total_amount ?? $order->final_price ?? $order->estimated_price ?? 0));
        $custName = $order->customer?->name ?? 'Pelanggan';
        $weight = (float) ($order->actual_weight ?: $order->weight ?: 0);

        return "Pesanan {$orderNumber} di {$storeName}: Pelanggan {$custName}, Status '{$statusLabel}', Berat {$weight} kg, Total tagihan {$totalFmt}.";
    }

    /**
     * Chatbot Response: Menu Navigasi Nyata Dashboard & Tindakan.
     */
    public function getMenuNavigationResponse(string $normalizedMessage): string
    {
        $norm = strtolower($normalizedMessage);

        if (str_contains($norm, 'tambah layanan') || (str_contains($norm, 'layanan') && (str_contains($norm, 'mana') || str_contains($norm, 'tambah') || str_contains($norm, 'buat')))) {
            return "Untuk menambah layanan baru, buka menu **Layanan** di URL `/store/services` lalu klik tombol **Tambah Layanan**.";
        }

        if (str_contains($norm, 'assign kurir') || str_contains($norm, 'tugaskan kurir') || str_contains($norm, 'atur kurir')) {
            return "Untuk menugaskan (assign) kurir pada pesanan, buka menu **Pesanan** di `/store/orders`, pilih pesanan yang bersangkutan, lalu tentukan kurir terikat untuk penjemputan atau pengantaran.";
        }

        if (str_contains($norm, 'jam operasional') || str_contains($norm, 'radius') || str_contains($norm, 'buka tutup')) {
            return "Pengaturan jam operasional, radius layanan gerai, dan status buka/tutup toko dapat diakses melalui menu **Pengaturan** di `/store/settings`.";
        }

        if (str_contains($norm, 'rekrut kurir') || str_contains($norm, 'pelamar')) {
            return "Daftar pelamar kurir terverifikasi yang siap direkrut dapat dilihat melalui menu **Kurir** di `/store/couriers`.";
        }

        $lines = ["Daftar menu navigasi pada Portal Admin Toko LaundryKu:"];
        foreach (self::MENU_ITEMS as $item) {
            $lines[] = "- **{$item['title']}** (`{$item['path']}`): {$item['desc']}";
        }

        return implode("\n", $lines);
    }

    /**
     * Chatbot Response: Identitas User & Toko.
     */
    public function getIdentityResponse(User $user, Store $store, string $normalizedMessage): string
    {
        $norm = strtolower($normalizedMessage);
        $storeName = $this->getStoreDisplayName($store);
        $roleLabel = ($user->role === 'store_owner' || (int) $store->owner_user_id === (int) $user->id) ? 'Pemilik Toko (Store Owner)' : 'Admin Toko (Store Admin)';

        if (str_contains($norm, 'toko saya') || str_contains($norm, 'nama toko') || str_contains($norm, 'gerai saya')) {
            $statusApproval = match($store->status) {
                'approved' => 'Disetujui (Approved)',
                'pending' => 'Menunggu Persetujuan (Pending)',
                'rejected' => 'Ditolak (Rejected)',
                default => ucfirst($store->status ?? 'pending'),
            };
            $statusOperasional = $store->is_active ? 'Aktif (Buka)' : 'Nonaktif (Tutup)';
            $address = $store->address ?? 'Alamat belum diatur';

            return "Toko yang Anda kelola adalah **{$storeName}**.\n- Status Persetujuan: {$statusApproval}\n- Status Operasional: {$statusOperasional}\n- Alamat: {$address}";
        }

        return "Anda login sebagai **{$user->name}** dengan peran **{$roleLabel}** di {$storeName} (Email: {$user->email}, Telepon: {$user->phone}).";
    }
}
