<?php

namespace App\Services\Ai;

use App\Enums\OrderStatus;
use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AiDataQueryService
{
    /**
     * Complete explicit mapping of ALL 19 OrderStatus enum cases.
     * Guaranteed that no OrderStatus is left unmapped.
     */
    public const ORDER_STATUS_MAP = [
        'created' => [
            'label' => 'Pesanan Dibuat',
            'washing_stage' => 'before_washing',
            'stage_desc' => 'pesanan baru dibuat dan menunggu verifikasi serta penugasan kurir jemput',
            'next_step' => 'Menunggu penugasan kurir penjemputan oleh sistem atau toko.',
        ],
        'waiting_pickup' => [
            'label' => 'Menunggu Penjemputan',
            'washing_stage' => 'before_washing',
            'stage_desc' => 'menunggu kurir datang mengambil cucian di alamat Anda',
            'next_step' => 'Siapkan pakaian kotor Anda untuk diserahkan saat kurir tiba.',
        ],
        'courier_assigned' => [
            'label' => 'Kurir Penjemputan Ditugaskan',
            'washing_stage' => 'before_washing',
            'stage_desc' => 'kurir penjemputan telah ditugaskan dan bersiap menuju lokasi Anda',
            'next_step' => 'Kurir akan segera bergerak menuju alamat penjemputan Anda.',
        ],
        'courier_to_customer' => [
            'label' => 'Kurir Menuju Lokasi Anda',
            'washing_stage' => 'before_washing',
            'stage_desc' => 'kurir sedang dalam perjalanan menuju alamat Anda untuk mengambil cucian',
            'next_step' => 'Pastikan nomor telepon Anda aktif agar kurir mudah menghubungi.',
        ],
        'picked_up' => [
            'label' => 'Cucian Berhasil Dijemput',
            'washing_stage' => 'before_washing',
            'stage_desc' => 'pakaian kotor telah berhasil diambil oleh kurir',
            'next_step' => 'Kurir akan mengantar pakaian Anda ke outlet toko laundry.',
        ],
        'in_transit_to_store' => [
            'label' => 'Dalam Perjalanan ke Toko',
            'washing_stage' => 'before_washing',
            'stage_desc' => 'pakaian Anda sedang dibawa kurir menuju outlet toko laundry',
            'next_step' => 'Menunggu kedatangan dan serah terima cucian di outlet toko.',
        ],
        'received_by_store' => [
            'label' => 'Diterima di Outlet Toko',
            'washing_stage' => 'before_washing',
            'stage_desc' => 'pakaian sudah tiba di outlet toko dan masuk antrean penimbangan',
            'next_step' => 'Pihak toko akan memeriksa dan menimbang cucian Anda.',
        ],
        'weighed' => [
            'label' => 'Selesai Ditimbang',
            'washing_stage' => 'before_washing',
            'stage_desc' => 'pakaian telah selesai ditimbang dan rincian harga telah dihitung',
            'next_step' => 'Selesaikan pembayaran tagihan agar pencucian dapat segera dimulai.',
        ],
        'payment_required' => [
            'label' => 'Menunggu Pembayaran',
            'washing_stage' => 'before_washing',
            'stage_desc' => 'tagihan telah terbit dan menunggu pembayaran dari Anda',
            'next_step' => 'Silakan lakukan pembayaran melalui menu Detail Pesanan atau Pembayaran.',
        ],
        'paid' => [
            'label' => 'Pembayaran Berhasil',
            'washing_stage' => 'before_washing',
            'stage_desc' => 'pembayaran telah lunas dan cucian masuk antrean mesin cuci',
            'next_step' => 'Pihak toko akan segera memasukkan cucian ke proses pencucian.',
        ],
        'processing' => [
            'label' => 'Sedang Dicuci & Diproses',
            'washing_stage' => 'washing',
            'stage_desc' => 'pakaian Anda saat ini sedang dalam proses pencucian, pengeringan, atau setrika',
            'next_step' => 'Pihak toko sedang menyelesaikan proses pencucian pakaian Anda.',
        ],
        'ready_for_delivery' => [
            'label' => 'Pencucian Selesai (Siap Diantar)',
            'washing_stage' => 'after_washing',
            'stage_desc' => 'pencucian sudah selesai, pakaian bersih telah dikemas rapi dan siap diantar',
            'next_step' => 'Menunggu penugasan kurir pengantaran menuju alamat Anda.',
        ],
        'courier_assigned_for_delivery' => [
            'label' => 'Kurir Pengantaran Ditugaskan',
            'washing_stage' => 'after_washing',
            'stage_desc' => 'pencucian telah selesai dan kurir pengantaran telah ditugaskan mengambil paket',
            'next_step' => 'Kurir akan mengambil pakaian bersih dari toko dan mengantarkannya ke Anda.',
        ],
        'out_for_delivery' => [
            'label' => 'Sedang Diantar Kurir',
            'washing_stage' => 'after_washing',
            'stage_desc' => 'pencucian telah selesai dan kurir sedang dalam perjalanan membawa pakaian ke alamat Anda',
            'next_step' => 'Siapkan diri Anda atau penerima di lokasi untuk menerima pakaian bersih.',
        ],
        'delivered' => [
            'label' => 'Pakaian Telah Sampai (Terkirim)',
            'washing_stage' => 'after_washing',
            'stage_desc' => 'pakaian bersih telah berhasil diserahkan kurir ke alamat Anda',
            'next_step' => 'Silakan periksa cucian Anda dan selesaikan konfirmasi di aplikasi.',
        ],
        'completed' => [
            'label' => 'Pesanan Selesai',
            'washing_stage' => 'after_washing',
            'stage_desc' => 'seluruh tahapan pesanan telah selesai secara penuh',
            'next_step' => 'Anda dapat memberikan ulasan dan rating untuk layanan toko laundry.',
        ],
        'cancelled' => [
            'label' => 'Pesanan Dibatalkan',
            'washing_stage' => 'cancelled_or_failed',
            'stage_desc' => 'pesanan ini telah dibatalkan sehingga tidak diproses',
            'next_step' => 'Jika ada dana yang telah dibayar, periksa status pengembalian dana (refund).',
        ],
        'pickup_failed' => [
            'label' => 'Penjemputan Gagal',
            'washing_stage' => 'cancelled_or_failed',
            'stage_desc' => 'penjemputan pakaian oleh kurir mengalami kendala atau gagal',
            'next_step' => 'Silakan hubungi layanan pelanggan atau jadwalkan ulang penjemputan.',
        ],
        'delivery_failed' => [
            'label' => 'Pengantaran Gagal',
            'washing_stage' => 'after_washing',
            'stage_desc' => 'pencucian selesai namun pengantaran pakaian oleh kurir mengalami kendala',
            'next_step' => 'Pihak toko/kurir akan menghubungi Anda untuk konfirmasi jadwal antar ulang.',
        ],
    ];

    protected AiOrderStatsService $statsService;

    public function __construct(AiOrderStatsService $statsService)
    {
        $this->statsService = $statsService;
    }

    /**
     * Dispatch deterministic read-only database query response for Capabilities A through K.
     * Returns null if query is procedural or does not match Layer 1 patterns.
     */
    public function handleDataQuery(?User $user, string $message): ?string
    {
        $normalized = mb_strtolower(trim($message));

        // Negative check: If procedural how-to ("cara memesan", "bagaimana cara melacak"), DO NOT intercept as data query!
        if ($this->isProceduralQuery($normalized)) {
            return null;
        }

        // Mutation / write requests (e.g. "ubah harga", "batalkan pesanan", "refund order") must NOT be intercepted as read data queries
        $writeTriggers = [
            'ubah', 'ganti', 'edit', 'update', 'hapus', 'delete', 'jadikan',
            'batalkan', 'cancel', 'refund order', 'minta refund', 'tolong refund',
            'ajukan refund', 'refund aku', 'refund dong',
        ];
        foreach ($writeTriggers as $wt) {
            if (str_contains($normalized, $wt)) {
                return null;
            }
        }

        // Exploit / injection attempts (e.g. "SELECT * FROM users", "tampilkan semua pesanan pelanggan", "email budi apa")
        if ($this->isExploitAttempt($normalized)) {
            return 'Maaf, permintaan tersebut melanggar kebijakan keamanan data dan tidak dapat diproses.';
        }

        // Capability K: Guest asking for personal data
        if (!$user) {
            if ($this->isPersonalDataInquiry($normalized)) {
                return 'Untuk melihat data pesanan, pembayaran, atau akun Anda, silakan masuk ke akun Anda terlebih dahulu melalui menu Masuk / Daftar (/auth).';
            }

            // Public inquiries for guests
            if ($this->isServicesOrPricesInquiry($normalized)) {
                return $this->getServicesAndPricesResponse($normalized);
            }
            if ($this->isStoreInquiry($normalized) && !str_contains($normalized, 'toko saya')) {
                return $this->getStoreInfoResponse($normalized);
            }
            if ($this->isBusinessRuleInquiry($normalized)) {
                return $this->getBusinessRulesResponse($normalized);
            }

            return null;
        }

        $roles = $this->getUserRoles($user);
        $isStoreManager = in_array('store_owner', $roles, true) || in_array('store_admin', $roles, true);
        $isCourier = in_array('courier', $roles, true);
        $isSuperAdmin = in_array('super_admin', $roles, true) || in_array('platform_owner', $roles, true);

        // Capability J: Super Admin queries
        if ($isSuperAdmin) {
            $adminRes = $this->handleSuperAdminQuery($user, $normalized);
            if ($adminRes !== null) {
                return $adminRes;
            }
        }

        // Capability D: Dynamic Business Rules (settings: COD, dispute window, payment window)
        // Checked first so couriers/mitras asking for COD limits or dispute rules get the business rules response
        if ($this->isBusinessRuleInquiry($normalized)) {
            return $this->getBusinessRulesResponse($normalized);
        }

        // Capability I: Courier specific task queries (progress / active tasks, excluding stats like 'berapa')
        if ($isCourier && $this->isCourierTaskInquiry($normalized)) {
            return $this->getCourierTaskProgressResponse($user, $normalized);
        }

        // Capability H: Store Owner / Admin queries
        if ($isStoreManager && $this->isStoreManagementInquiry($normalized)) {
            $storeRes = $this->handleStoreManagerQuery($user, $normalized);
            if ($storeRes !== null) {
                return $storeRes;
            }
        }

        // Capability C: Payments and refunds
        if ($this->isPaymentOrRefundInquiry($normalized)) {
            return $this->getPaymentAndRefundResponse($user, $normalized);
        }

        // Capability A: Order progress, tracking, washing stage ("sampai mana", "sudah dicuci belum", "status ORD-xxx")
        if ($this->isOrderProgressInquiry($normalized)) {
            return $this->getOrderProgressResponse($user, $normalized);
        }

        // Capability F: Disputes and reviews
        if ($this->isDisputeOrReviewInquiry($normalized)) {
            return $this->getDisputesAndReviewsResponse($user, $normalized);
        }

        // Capability G: Account & saved addresses count
        if ($this->isAccountInquiry($normalized)) {
            return $this->getAccountResponse($user, $normalized);
        }

        // Capability D: Services and prices
        if ($this->isServicesOrPricesInquiry($normalized)) {
            return $this->getServicesAndPricesResponse($normalized);
        }

        // Capability E: Store general information
        if ($this->isStoreInquiry($normalized) && !str_contains($normalized, 'pesanan masuk') && !str_contains($normalized, 'hari ini')) {
            return $this->getStoreInfoResponse($normalized);
        }

        return null;
    }

    /**
     * Capability A: Customer order progress, washing stage, and step details.
     */
    public function getOrderProgressResponse(User $user, string $normalizedMessage): string
    {
        // 1. Check if specific order number is mentioned (e.g. ORD-12345)
        preg_match('/ord-[a-z0-9-]+/i', $normalizedMessage, $matches);
        $orderNumber = !empty($matches[0]) ? strtoupper($matches[0]) : null;

        if ($orderNumber) {
            $order = Order::where('order_number', $orderNumber)->first();

            // Strict Tenant Isolation: Only owner customer can see it
            if (!$order || (int) $order->customer_id !== (int) $user->id) {
                return "Pesanan dengan nomor {$orderNumber} tidak ditemukan pada akun Anda.";
            }

            return $this->formatOrderProgressDetail($order, $normalizedMessage);
        }

        // 2. No order number specified -> evaluate customer active orders
        $activeOrders = Order::where('customer_id', $user->id)
            ->whereNotIn('status', ['completed', 'cancelled', 'pickup_failed', 'delivery_failed'])
            ->latest('order_date')
            ->latest('id')
            ->get();

        $isWashingInquiry = str_contains($normalizedMessage, 'dicuci')
            || str_contains($normalizedMessage, 'sudah jadi')
            || str_contains($normalizedMessage, 'cuci belum')
            || (str_contains($normalizedMessage, 'cuci') && (str_contains($normalizedMessage, 'sudah') || str_contains($normalizedMessage, 'belum') || str_contains($normalizedMessage, 'selesai') || str_contains($normalizedMessage, 'kelar') || str_contains($normalizedMessage, 'beres')));

        // Penyempurnaan 1: Pertanyaan "sudah dicuci belum" tanpa nomor pesanan dengan multiple pesanan aktif
        if ($activeOrders->count() > 1 && $isWashingInquiry) {
            $sortedOrders = $activeOrders->sortBy(fn($o) => $this->getWashingPriority($o->status))->take(3);
            $statements = [];
            $primaryNextStep = null;

            foreach ($sortedOrders as $ao) {
                $storeName = optional($ao->store)->name ?? 'Toko Laundry';
                $statusInfo = self::ORDER_STATUS_MAP[$ao->status] ?? null;

                if ($ao->status === 'processing') {
                    $statements[] = "{$ao->order_number} sedang dicuci di {$storeName}.";
                    if (!$primaryNextStep) {
                        $primaryNextStep = $statusInfo['next_step'] ?? null;
                    }
                } elseif ($ao->status === 'paid') {
                    $statements[] = "{$ao->order_number} belum dicuci, masuk antrean pencucian di {$storeName}.";
                    if (!$primaryNextStep) {
                        $primaryNextStep = $statusInfo['next_step'] ?? null;
                    }
                } elseif ($ao->status === 'payment_required') {
                    $statements[] = "{$ao->order_number} belum dicuci, menunggu pembayaran.";
                    $primaryNextStep = $statusInfo['next_step'] ?? 'Silakan lakukan pembayaran melalui menu Detail Pesanan atau Pembayaran.';
                } elseif (in_array($ao->status, ['ready_for_delivery', 'courier_assigned_for_delivery', 'out_for_delivery', 'delivered'], true)) {
                    $statements[] = "{$ao->order_number} sudah selesai dicuci di {$storeName}.";
                    if (!$primaryNextStep) {
                        $primaryNextStep = $statusInfo['next_step'] ?? null;
                    }
                } else {
                    $desc = $statusInfo['stage_desc'] ?? 'menunggu proses';
                    $statements[] = "{$ao->order_number} belum dicuci ({$desc}).";
                    if (!$primaryNextStep) {
                        $primaryNextStep = $statusInfo['next_step'] ?? null;
                    }
                }
            }

            $body = implode(' ', $statements);
            if ($primaryNextStep) {
                return "{$body} Langkah berikutnya: {$primaryNextStep}";
            }
            return $body;
        }

        $isWeightInquiry = str_contains($normalizedMessage, 'timbang')
            || str_contains($normalizedMessage, 'berat')
            || str_contains($normalizedMessage, 'kilo')
            || str_contains($normalizedMessage, 'bobot');

        // Check if query is specifically asking about weight when multiple active orders exist
        if ($activeOrders->count() > 1 && $isWeightInquiry) {
            $statements = [];
            foreach ($activeOrders->take(3) as $ao) {
                $statusInfo = self::ORDER_STATUS_MAP[$ao->status] ?? null;
                $label = $statusInfo['label'] ?? $ao->status;
                $weight = (float) ($ao->actual_weight ?: $ao->weight ?: 0);
                if ($weight > 0) {
                    $statements[] = "Pesanan nomor {$ao->order_number} sudah ditimbang dengan berat {$weight} kg (status: '{$label}').";
                } else {
                    $statements[] = "Pesanan nomor {$ao->order_number} belum ditimbang oleh pihak toko.";
                }
            }
            return implode(' ', $statements);
        }

        // Penyempurnaan 2: Respon pesanan aktif umum (tanpa pengantar repetitif, padat & ada langkah berikutnya)
        if ($activeOrders->count() > 1) {
            $activeList = $activeOrders->take(3);
            $summaryParts = [];
            $latestActive = $activeOrders->first();
            $latestStatusInfo = self::ORDER_STATUS_MAP[$latestActive->status] ?? null;

            foreach ($activeList as $ao) {
                $statusInfo = self::ORDER_STATUS_MAP[$ao->status] ?? null;
                $label = $statusInfo['label'] ?? $ao->status;
                $storeName = optional($ao->store)->name ?? 'Toko Laundry';
                $summaryParts[] = "{$ao->order_number} ({$label} di {$storeName})";
            }

            $listText = implode(', ', $summaryParts);
            $nextStep = $latestStatusInfo['next_step'] ?? 'Silakan pantau perkembangan pesanan Anda di aplikasi.';

            return "Anda memiliki {$activeOrders->count()} pesanan aktif: {$listText}. Langkah berikutnya: {$nextStep}";
        }

        if ($activeOrders->count() === 1) {
            return $this->formatOrderProgressDetail($activeOrders->first(), $normalizedMessage);
        }

        // 3. No active orders -> check historical last order
        $lastOrder = Order::where('customer_id', $user->id)
            ->latest('order_date')
            ->latest('id')
            ->first();

        if (!$lastOrder) {
            return 'Anda belum memiliki pesanan di LaundryKu.';
        }

        // If user specifically asked an aspect ("sudah dibayar", "sudah dicuci", "sudah ditimbang", "berat"), format the detail!
        if (str_contains($normalizedMessage, 'dibayar') || str_contains($normalizedMessage, 'dicuci') || str_contains($normalizedMessage, 'ditimbang') || str_contains($normalizedMessage, 'timbang') || str_contains($normalizedMessage, 'berat') || str_contains($normalizedMessage, 'kilo') || str_contains($normalizedMessage, 'bobot') || str_contains($normalizedMessage, 'lunas')) {
            return $this->formatOrderProgressDetail($lastOrder, $normalizedMessage);
        }

        $dateWib = $this->statsService->formatWibDate($lastOrder->order_date ?? $lastOrder->created_at);
        $statusInfo = self::ORDER_STATUS_MAP[$lastOrder->status] ?? null;
        $label = $statusInfo['label'] ?? $lastOrder->status;

        return "Saat ini Anda tidak memiliki pesanan aktif. Pesanan terakhir Anda adalah nomor {$lastOrder->order_number} dengan status '{$label}' pada tanggal {$dateWib}.";
    }

    /**
     * Format detailed honest answer for a specific order based on query aspect.
     */
    protected function formatOrderProgressDetail(Order $order, string $normalizedMessage): string
    {
        $statusInfo = self::ORDER_STATUS_MAP[$order->status] ?? [
            'label' => $order->status,
            'washing_stage' => 'washing',
            'stage_desc' => 'dalam penanganan pihak toko',
            'next_step' => 'Silakan pantau perkembangan pesanan Anda di aplikasi.',
        ];

        $storeName = optional($order->store)->name ?? 'Toko Laundry';

        // Aspect A: "sudah dicuci belum", "apakah pesanan saya sudah dicuci", "cucian saya sudah jadi belum"
        if (str_contains($normalizedMessage, 'dicuci') || str_contains($normalizedMessage, 'sudah jadi') || str_contains($normalizedMessage, 'cuci belum') || (str_contains($normalizedMessage, 'cuci') && (str_contains($normalizedMessage, 'sudah') || str_contains($normalizedMessage, 'belum') || str_contains($normalizedMessage, 'selesai') || str_contains($normalizedMessage, 'kelar') || str_contains($normalizedMessage, 'beres')))) {
            return match ($statusInfo['washing_stage']) {
                'before_washing' => "Cucian Anda nomor {$order->order_number} belum dicuci. Tahap saat ini: {$statusInfo['stage_desc']}. Langkah berikutnya: {$statusInfo['next_step']}",
                'washing' => "Cucian Anda nomor {$order->order_number} saat ini sedang dicuci dan diproses di {$storeName}. Langkah berikutnya: {$statusInfo['next_step']}",
                'after_washing' => "Pencucian pakaian Anda nomor {$order->order_number} sudah selesai! Tahap saat ini: {$statusInfo['stage_desc']}. Langkah berikutnya: {$statusInfo['next_step']}",
                'cancelled_or_failed' => "Pesanan nomor {$order->order_number} berstatus '{$statusInfo['label']}' ({$statusInfo['stage_desc']}) sehingga tidak dalam proses pencucian. {$statusInfo['next_step']}",
                default => "Status pencucian pesanan {$order->order_number}: {$statusInfo['label']}.",
            };
        }

        // Aspect B: "sudah ditimbang belum", "berapa beratnya", "timbang"
        if (str_contains($normalizedMessage, 'ditimbang') || str_contains($normalizedMessage, 'timbang') || str_contains($normalizedMessage, 'berat') || str_contains($normalizedMessage, 'kilo') || str_contains($normalizedMessage, 'bobot')) {
            $isWeighed = !empty($order->weighed_at) || (float) ($order->actual_weight ?? 0) > 0 || in_array($order->status, [
                'weighed', 'payment_required', 'paid', 'processing', 'ready_for_delivery',
                'courier_assigned_for_delivery', 'out_for_delivery', 'delivered', 'completed',
            ], true);

            if ($isWeighed) {
                $weight = (float) ($order->actual_weight ?: $order->weight);
                return "Pesanan nomor {$order->order_number} sudah ditimbang dengan berat {$weight} kg. Tahap status saat ini: '{$statusInfo['label']}'.";
            }

            return "Pesanan nomor {$order->order_number} belum ditimbang oleh pihak toko. Tahap saat ini: {$statusInfo['stage_desc']}.";
        }

        // Aspect C: "sudah dibayar belum", "status bayar"
        if (str_contains($normalizedMessage, 'dibayar') || str_contains($normalizedMessage, 'status bayar') || str_contains($normalizedMessage, 'lunas') || str_contains($normalizedMessage, 'bayar') || str_contains($normalizedMessage, 'tagihan')) {
            $total = (float) ($order->total_amount ?: $order->total_price ?: 0);
            if ($order->payment_status === 'paid') {
                $method = strtoupper($order->payment_method ?: 'Online/COD');
                return "Pesanan nomor {$order->order_number} sudah dibayar (Lunas) sebesar Rp " . number_format($total, 0, ',', '.') . " melalui {$method}. Status pembayaran pesanan: Lunas.";
            }

            return "Pesanan nomor {$order->order_number} belum dibayar. Total tagihan: Rp " . number_format($total, 0, ',', '.') . ". Status pembayaran pesanan: Menunggu Pembayaran. Silakan lakukan pembayaran melalui menu Detail Pesanan.";
        }

        // Aspect D: "kurirnya sudah jalan belum", "kurir sampai mana", "kapan diantar"
        if (str_contains($normalizedMessage, 'kurir') || str_contains($normalizedMessage, 'jalan belum') || str_contains($normalizedMessage, 'kapan diantar')) {
            if ($order->status === OrderStatus::COURIER_TO_CUSTOMER->value) {
                return "Kurir penjemputan saat ini sedang dalam perjalanan menuju lokasi Anda untuk pesanan nomor {$order->order_number}.";
            }
            if ($order->status === OrderStatus::OUT_FOR_DELIVERY->value) {
                return "Kurir pengantaran saat ini sedang dalam perjalanan membawa pakaian bersih ke lokasi Anda untuk pesanan nomor {$order->order_number}.";
            }
            if (in_array($order->status, [OrderStatus::COURIER_ASSIGNED->value, OrderStatus::COURIER_ASSIGNED_FOR_DELIVERY->value], true)) {
                return "Kurir telah ditugaskan untuk pesanan nomor {$order->order_number} dan bersiap menuju lokasi.";
            }

            return "Kurir belum bergerak menuju alamat Anda. Tahap saat ini untuk pesanan nomor {$order->order_number}: {$statusInfo['stage_desc']}.";
        }

        // General status response
        $dateWib = $this->statsService->formatWibDate($order->order_date ?? $order->created_at);
        return "Pesanan Anda nomor {$order->order_number} berstatus '{$statusInfo['label']}' di toko {$storeName} (Tahap: {$statusInfo['stage_desc']}) per tanggal {$dateWib}. Langkah berikutnya: {$statusInfo['next_step']}";
    }

    /**
     * Capability C: Customer payments and refunds status.
     */
    public function getPaymentAndRefundResponse(User $user, string $normalizedMessage): string
    {
        // Refund specific inquiry
        if (str_contains($normalizedMessage, 'refund') || str_contains($normalizedMessage, 'pengembalian dana')) {
            $refund = Refund::where('requested_by', $user->id)
                ->orWhereIn('order_id', Order::where('customer_id', $user->id)->pluck('id'))
                ->latest('id')
                ->first();

            if (!$refund) {
                return 'Anda tidak memiliki catatan pengajuan pengembalian dana (refund) di LaundryKu.';
            }

            $amountFmt = 'Rp ' . number_format((float) $refund->amount, 0, ',', '.');
            $statusLabel = match ($refund->status) {
                'requested' => 'Menunggu Persetujuan Admin',
                'approved' => 'Disetujui',
                'processing' => 'Sedang Diproses',
                'completed' => 'Berhasil Dikembalikan',
                'rejected' => 'Ditolak',
                default => $refund->status,
            };

            return "Pengajuan refund untuk pesanan terkait bernilai {$amountFmt} berstatus '{$statusLabel}'. Alasan: {$refund->reason}.";
        }

        // Payment status inquiry
        $order = Order::where('customer_id', $user->id)
            ->latest('order_date')
            ->latest('id')
            ->first();

        if (!$order) {
            return 'Anda belum memiliki tagihan atau riwayat pembayaran di LaundryKu.';
        }

        $payment = Payment::where('order_id', $order->id)->latest('id')->first();
        $totalFmt = 'Rp ' . number_format((float) ($order->total_amount ?: $order->total_price ?: 0), 0, ',', '.');

        if ($order->payment_status === 'paid') {
            $method = strtoupper($order->payment_method ?: 'Xendit');
            $paidTime = $payment && $payment->paid_at ? ' pada ' . $this->statsService->formatWibDate($payment->paid_at) : '';
            return "Status pembayaran pesanan nomor {$order->order_number}: sudah dibayar (Lunas) sebesar {$totalFmt} melalui {$method}{$paidTime}.";
        }

        $deadlineText = '';
        if ($payment && $payment->expires_at) {
            $deadlineText = ' Batas waktu pembayaran: ' . $this->statsService->formatWibDate($payment->expires_at) . '.';
        }

        return "Tagihan pesanan nomor {$order->order_number} sebesar {$totalFmt} berstatus 'Belum Dibayar'.{$deadlineText} Silakan lakukan pembayaran di menu Detail Pesanan.";
    }

    /**
     * Capability D: Services and Prices.
     */
    public function getServicesAndPricesResponse(string $normalizedMessage): string
    {
        $services = Service::where('is_active', true)->orderBy('id')->get();

        if ($services->isEmpty()) {
            return 'Saat ini belum ada layanan aktif yang terdaftar di LaundryKu.';
        }

        $totalServices = $services->count();
        $displayServices = $services->take(5);

        $list = [];
        foreach ($displayServices as $idx => $s) {
            $num = $idx + 1;
            $priceSample = \App\Models\ServicePrice::where('service_id', $s->id)->where('is_active', true)->first();
            $priceText = '';
            if ($priceSample) {
                $priceFmt = number_format((float) $priceSample->price_per_unit, 0, ',', '.');
                $priceText = " (mulai Rp {$priceFmt}/{$priceSample->unit})";
            }
            $desc = !empty($s->description) ? " - {$s->description}" : '';
            $list[] = "{$num}. {$s->name}{$priceText}{$desc}";
        }

        $text = "Tarif dan layanan yang tersedia di LaundryKu:\n" . implode("\n", $list);

        if ($totalServices > 5) {
            $text .= "\n(Menampilkan 5 dari {$totalServices} layanan. Rincian harga per toko dapat dilihat pada menu Layanan & Toko).";
        }

        return $text;
    }

    /**
     * Capability D: Dynamic Business Rules read strictly from `settings` table.
     */
    public function getBusinessRulesResponse(string $normalizedMessage): string
    {
        // 1. COD rules
        if (str_contains($normalizedMessage, 'cod')) {
            $codMax = DB::table('settings')->where('key', 'COD_MAX_AMOUNT')->value('value') ?? '500000';
            $codMaxFmt = 'Rp ' . number_format((float) $codMax, 0, ',', '.');
            return "Batas nominal maksimum pesanan menggunakan metode pembayaran di tempat (COD) adalah {$codMaxFmt}.";
        }

        // 2. Complaint / dispute window
        if (str_contains($normalizedMessage, 'komplain') || str_contains($normalizedMessage, 'dispute') || str_contains($normalizedMessage, 'keluhan')) {
            $disputeHours = DB::table('settings')->where('key', 'DISPUTE_WINDOW_HOURS')->value('value') ?? '24';
            return "Batas waktu pengajuan komplain/dispute adalah {$disputeHours} jam setelah pesanan diterima (delivered).";
        }

        // 3. Payment window
        if (str_contains($normalizedMessage, 'bayar') || str_contains($normalizedMessage, 'invoice') || str_contains($normalizedMessage, 'tenggat')) {
            $payHours = DB::table('settings')->where('key', 'PAYMENT_WINDOW_HOURS')->value('value') ?? '1';
            return "Batas waktu penyelesaian pembayaran invoice online adalah {$payHours} jam setelah invoice diterbitkan.";
        }

        // 4. Customer Service Contact
        if (str_contains($normalizedMessage, 'cs') || str_contains($normalizedMessage, 'customer service') || str_contains($normalizedMessage, 'kontak') || str_contains($normalizedMessage, 'hubungi')) {
            return "Layanan Customer Service LaundryKu siap membantu Anda melalui WhatsApp di 0812-3456-7890 atau email bantuan@laundryku.test (Senin - Minggu, 08.00 - 21.00 WIB).";
        }

        return 'Informasi aturan bisnis LaundryKu dapat dicek pada menu Pengaturan & Kebijakan Aplikasi.';
    }

    /**
     * Capability E: Stores information.
     */
    public function getStoreInfoResponse(string $normalizedMessage): string
    {
        $stores = Store::where('status', 'approved')->where('is_active', true)->get();

        if ($stores->isEmpty()) {
            return 'Saat ini belum ada toko mitra aktif yang terdaftar di LaundryKu.';
        }

        // Check if specific store name is searched
        foreach ($stores as $st) {
            if (str_contains($normalizedMessage, mb_strtolower($st->name))) {
                $hours = '08:00 - 20:00 WIB';
                if (!empty($st->operational_hours)) {
                    if (is_array($st->operational_hours)) {
                        $parts = [];
                        foreach ($st->operational_hours as $day => $h) {
                            $parts[] = ucfirst($day) . ': ' . (is_array($h) ? json_encode($h) : $h);
                        }
                        $hours = implode(', ', $parts);
                    } else {
                        $hours = (string) $st->operational_hours;
                    }
                }
                $rating = number_format((float) ($st->average_rating ?? 5.0), 1);
                $reviews = (int) ($st->review_count ?? 0);
                $cod = $st->cod_enabled ? 'Mendukung COD' : 'Non-COD';

                return "Toko {$st->name} ({$st->city}): Jam operasional {$hours}. Rating: {$rating}/5 ({$reviews} ulasan). Layanan: {$cod}.";
            }
        }

        // General store list (max 5)
        $total = $stores->count();
        $sample = $stores->take(5);
        $list = [];
        foreach ($sample as $idx => $st) {
            $num = $idx + 1;
            $rating = number_format((float) ($st->average_rating ?? 5.0), 1);
            $list[] = "{$num}. {$st->name} ({$st->city}) - Rating {$rating}";
        }

        $res = "Toko laundry mitra aktif di LaundryKu:\n" . implode("\n", $list);
        if ($total > 5) {
            $res .= "\n(Menampilkan 5 dari {$total} toko mitra. Cari nama toko untuk melihat jam buka dan detail layanan).";
        }

        return $res;
    }

    /**
     * Capability F: Customer disputes and reviews.
     */
    public function getDisputesAndReviewsResponse(User $user, string $normalizedMessage): string
    {
        if (str_contains($normalizedMessage, 'komplain') || str_contains($normalizedMessage, 'dispute') || str_contains($normalizedMessage, 'keluhan')) {
            $dispute = Dispute::where('opened_by', $user->id)->latest('id')->first();

            if (!$dispute) {
                return 'Anda belum memiliki riwayat pengajuan komplain atau dispute di LaundryKu.';
            }

            $statusLabel = match ($dispute->status) {
                'open' => 'Sedang Ditinjau',
                'investigating' => 'Dalam Penyelidikan',
                'resolved' => 'Selesai / Teratasi',
                'rejected' => 'Ditolak',
                default => $dispute->status,
            };

            $resNote = !empty($dispute->resolution_note) ? " Hasil resolusi: {$dispute->resolution_note}." : '';
            return "Komplain Anda nomor {$dispute->complaint_number} berstatus '{$statusLabel}'. Alasan: {$dispute->reason}.{$resNote}";
        }

        // Review inquiry
        $review = Review::where('customer_id', $user->id)->latest('id')->first();

        if (!$review) {
            return 'Anda belum pernah memberikan ulasan atau rating untuk pesanan di LaundryKu.';
        }

        $storeName = optional($review->store)->name ?? 'Toko Laundry';
        return "Ulasan terakhir Anda untuk toko {$storeName}: {$review->rating}/5 bintang (\"{$review->comment}\").";
    }

    /**
     * Capability G: Account information and saved address count (safe minimal disclosure).
     */
    public function getAccountResponse(User $user, string $normalizedMessage): string
    {
        $roles = $this->getUserRoles($user);
        $roleLabels = [
            'customer' => 'Pelanggan',
            'courier' => 'Kurir',
            'store_owner' => 'Pemilik Toko',
            'store_admin' => 'Admin Toko',
            'platform_owner' => 'Pemilik Platform',
            'super_admin' => 'Super Admin',
        ];

        $translated = [];
        foreach ($roles as $r) {
            $translated[] = $roleLabels[$r] ?? $r;
        }
        $roleStr = implode(', ', $translated);

        $storeInfo = '';
        $storeIds = $this->getManagedStoreIds($user);
        if (!empty($storeIds)) {
            $storeNames = Store::whereIn('id', $storeIds)->pluck('name')->all();
            if (!empty($storeNames)) {
                $storeInfo = ' Toko yang dikelola: ' . implode(', ', $storeNames) . '.';
            }
        }

        $addressCount = DB::table('customer_addresses')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->count();

        return "Akun Anda terdaftar atas nama {$user->name} dengan peran {$roleStr}.{$storeInfo} Anda memiliki {$addressCount} alamat tersimpan di LaundryKu.";
    }

    /**
     * Capability H: Store Owner / Admin operations.
     */
    public function handleStoreManagerQuery(User $user, string $normalizedMessage): ?string
    {
        $storeIds = $this->getManagedStoreIds($user);

        if (empty($storeIds)) {
            return 'Anda belum memiliki toko mitra yang dikelola.';
        }

        // Check if query is looking up specific order number
        preg_match('/ord-[a-z0-9-]+/i', $normalizedMessage, $matches);
        $orderNumber = !empty($matches[0]) ? strtoupper($matches[0]) : null;

        if ($orderNumber) {
            $order = Order::where('order_number', $orderNumber)->first();

            // Ownership filter: must be in store's managed IDs
            if (!$order || !in_array((int) $order->store_id, $storeIds, true)) {
                return "Pesanan dengan nomor {$orderNumber} tidak ditemukan pada toko yang Anda kelola.";
            }

            $statusInfo = self::ORDER_STATUS_MAP[$order->status] ?? null;
            $label = $statusInfo['label'] ?? $order->status;
            $weight = (float) ($order->actual_weight ?: $order->weight ?: 0);
            $total = (float) ($order->total_amount ?: $order->total_price ?: 0);
            $custName = optional($order->customer)->name ?? 'Pelanggan';

            return "Pesanan {$orderNumber} di toko Anda: Pelanggan {$custName}, Status '{$label}', Berat {$weight} kg, Total tagihan Rp " . number_format($total, 0, ',', '.') . ".";
        }

        // Check if query is asking for applicants / courier candidates
        if (str_contains($normalizedMessage, 'pelamar') || str_contains($normalizedMessage, 'kandidat') || str_contains($normalizedMessage, 'rekrut')) {
            return $this->getStoreApplicantsResponse($user);
        }

        // Check if query is asking for store couriers
        if (str_contains($normalizedMessage, 'kurir') && !str_contains($normalizedMessage, 'tugas')) {
            return $this->getStoreCouriersResponse($user);
        }

        // Check if query is asking for store omzet / revenue
        if (str_contains($normalizedMessage, 'omset') || str_contains($normalizedMessage, 'omzet') || str_contains($normalizedMessage, 'pendapatan')) {
            return $this->getStoreRevenueResponse($user, $normalizedMessage);
        }

        // Check if query is asking for store services & prices
        if (str_contains($normalizedMessage, 'harga') || str_contains($normalizedMessage, 'layanan') || str_contains($normalizedMessage, 'tarif')) {
            return $this->getStoreServicesResponse($user);
        }

        // Check if query is asking for store complaints
        if (str_contains($normalizedMessage, 'komplain') || str_contains($normalizedMessage, 'keluhan') || str_contains($normalizedMessage, 'dispute')) {
            return $this->getStoreComplaintsResponse($user);
        }

        // Check if query is asking for store operational hours
        if (str_contains($normalizedMessage, 'jam') || str_contains($normalizedMessage, 'buka') || str_contains($normalizedMessage, 'operasional')) {
            return $this->getStoreHoursResponse($user);
        }

        // Check if query is asking for store rating & reviews
        if (str_contains($normalizedMessage, 'rating') || str_contains($normalizedMessage, 'ulasan toko') || str_contains($normalizedMessage, 'ulasan')) {
            $store = Store::whereIn('id', $storeIds)->first();
            $rating = number_format((float) ($store->average_rating ?? 5.0), 1);
            $revCount = (int) ($store->review_count ?? 0);
            return "Toko {$store->name} memiliki rating {$rating}/5 dari {$revCount} ulasan pelanggan.";
        }

        return null;
    }

    public function getStoreRevenueResponse(User $user, string $message = ''): string
    {
        $storeIds = $this->getManagedStoreIds($user);
        if (empty($storeIds)) {
            return 'Anda belum memiliki toko mitra yang dikelola.';
        }

        $storeNames = Store::whereIn('id', $storeIds)->pluck('name')->implode(', ');
        $query = Order::whereIn('store_id', $storeIds);
        $totalOrders = (clone $query)->count();

        if ($totalOrders === 0) {
            return "Toko yang Anda kelola ({$storeNames}) belum menerima pesanan.";
        }

        $storageTimezone = config('app.timezone', 'UTC');
        $startOfTodayStorage = Carbon::now('Asia/Jakarta')->startOfDay()->setTimezone($storageTimezone);
        $endOfTodayStorage = Carbon::now('Asia/Jakarta')->endOfDay()->setTimezone($storageTimezone);

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

        $norm = strtolower($message);
        if (str_contains($norm, 'hari ini')) {
            return "Omzet hari ini untuk toko {$storeNames} adalah {$todayFmt} (hanya transaksi yang telah terbayar). Total omzet keseluruhan: {$totalFmt}.";
        }

        return "Ringkasan omzet toko {$storeNames} (hanya transaksi terbayar):\n- Omzet hari ini: {$todayFmt}\n- Total omzet: {$totalFmt}";
    }

    public function getStoreCouriersResponse(User $user): string
    {
        $storeIds = $this->getManagedStoreIds($user);
        if (empty($storeIds)) {
            return 'Anda belum memiliki toko mitra yang dikelola.';
        }

        $store = Store::whereIn('id', $storeIds)->first();
        $couriers = Courier::whereIn('store_id', $storeIds)->with('user')->get();

        if ($couriers->isEmpty()) {
            return "Toko {$store->name} belum memiliki kurir terikat.";
        }

        $lines = ["Toko {$store->name} memiliki {$couriers->count()} kurir terikat:"];
        foreach ($couriers as $c) {
            $name = $c->user?->name ?? 'Kurir';
            $vehicle = $c->vehicle_type ?? 'Motor';
            $status = $c->is_available ? 'siap tugas' : 'tidak aktif';
            $lines[] = "- {$name} ({$vehicle}, status: {$status})";
        }

        return implode("\n", $lines);
    }

    public function getStoreApplicantsResponse(User $user): string
    {
        $storeIds = $this->getManagedStoreIds($user);
        if (empty($storeIds)) {
            return 'Anda belum memiliki toko mitra yang dikelola.';
        }

        $store = Store::whereIn('id', $storeIds)->first();

        $rejectedIds = DB::table('audit_logs')
            ->where('action', 'STORE_COURIER_REJECT')
            ->where('resource_type', 'courier')
            ->where(function ($q) use ($user, $store) {
                $q->where('actor_id', $user->id)
                  ->orWhere('after_value', 'like', '%"store_id":' . $store->id . '%')
                  ->orWhere('after_value', 'like', '%"store_id":"' . $store->id . '"%');
            })
            ->pluck('resource_id')
            ->toArray();

        $candidatesQuery = Courier::whereNull('store_id')
            ->where('is_verified', true);

        if (!empty($rejectedIds)) {
            $candidatesQuery->whereNotIn('id', $rejectedIds);
        }

        $candidates = $candidatesQuery->with('user')->get();

        if ($candidates->isEmpty()) {
            return "Saat ini belum ada pelamar kurir baru yang siap direkrut untuk toko {$store->name}.";
        }

        $lines = ["Toko {$store->name} memiliki {$candidates->count()} kandidat/pelamar kurir siap direkrut:"];
        foreach ($candidates as $c) {
            $name = $c->user?->name ?? 'Pelamar Kurir';
            $vehicle = $c->vehicle_type ?? 'Motor';
            $lines[] = "- {$name} ({$vehicle}, status: terverifikasi)";
        }

        return implode("\n", $lines);
    }

    public function getStoreServicesResponse(User $user): string
    {
        $storeIds = $this->getManagedStoreIds($user);
        if (empty($storeIds)) {
            return 'Anda belum memiliki toko mitra yang dikelola.';
        }

        $store = Store::whereIn('id', $storeIds)->first();
        $prices = ServicePrice::whereIn('store_id', $storeIds)
            ->where('is_active', true)
            ->with('service')
            ->get();

        if ($prices->isEmpty()) {
            $storeName = $store->name ?? 'Toko Anda';
            return "Toko {$storeName} belum memiliki daftar layanan dan harga aktif yang terdaftar.";
        }

        $lines = ["Daftar layanan dan harga di toko {$store->name}:"];
        foreach ($prices as $p) {
            $svcName = optional($p->service)->name ?? 'Layanan';
            $unit = optional($p->service)->unit ?? 'kg';
            $rp = number_format((float) $p->price_per_unit, 0, ',', '.');
            $lines[] = "- {$svcName}: Rp {$rp}/{$unit}";
        }

        return implode("\n", $lines);
    }

    public function getStoreComplaintsResponse(User $user): string
    {
        $storeIds = $this->getManagedStoreIds($user);
        if (empty($storeIds)) {
            return 'Anda belum memiliki toko mitra yang dikelola.';
        }

        $store = Store::whereIn('id', $storeIds)->first();
        $disputes = Dispute::whereHas('order', function ($q) use ($storeIds) {
            $q->whereIn('store_id', $storeIds);
        })->latest()->take(5)->get();

        if ($disputes->isEmpty()) {
            return "Saat ini tidak ada komplain pelanggan pada toko {$store->name}.";
        }

        $lines = ["Toko {$store->name} memiliki {$disputes->count()} komplain pelanggan:"];
        foreach ($disputes as $d) {
            $ordNum = optional($d->order)->order_number ?? 'Pesanan';
            $reason = $d->reason ?: 'Keluhan pelanggan';
            $lines[] = "- {$ordNum}: {$reason} (status: {$d->status})";
        }

        return implode("\n", $lines);
    }

    public function getStoreHoursResponse(User $user): string
    {
        $storeIds = $this->getManagedStoreIds($user);
        if (empty($storeIds)) {
            return 'Anda belum memiliki toko mitra yang dikelola.';
        }

        $store = Store::whereIn('id', $storeIds)->first();
        if (!$store) {
            return 'Data toko Anda tidak ditemukan.';
        }

        $hours = $store->operational_hours ?: 'Senin - Minggu: 08:00 - 20:00 WIB';
        return "Jam operasional toko {$store->name}: {$hours}.";
    }

    /**
     * Capability I: Courier specific tasks progress, history, and store binding.
     */
    public function getCourierTaskProgressResponse(User $user, string $normalizedMessage): string
    {
        $courier = Courier::where('user_id', $user->id)->first();

        if (!$courier) {
            return 'Profil kurir Anda belum aktif atau belum memiliki tugas di LaundryKu.';
        }

        // 1. Courier inquiry about assigned store
        if (str_contains($normalizedMessage, 'toko') || str_contains($normalizedMessage, 'mitra') || str_contains($normalizedMessage, 'bertugas di')) {
            return $this->getCourierStoreResponse($user);
        }

        // 2. Courier inquiry about completed tasks history
        if (str_contains($normalizedMessage, 'riwayat') || str_contains($normalizedMessage, 'selesai') || str_contains($normalizedMessage, 'kemarin') || str_contains($normalizedMessage, 'sebelumnya')) {
            return $this->getCourierTaskHistoryResponse($user);
        }

        $activeTask = CourierTask::where('courier_id', $courier->id)
            ->whereIn('status', ['assigned', 'accepted', 'arrived', 'picked_up'])
            ->latest('id')
            ->first();

        if (!$activeTask) {
            return $this->statsService->getCourierStats($user);
        }

        $taskType = $activeTask->task_type === 'pickup' ? 'Penjemputan' : 'Pengantaran';
        $orderNum = optional($activeTask->order)->order_number ?? 'Pesanan';

        return "Tugas kurir aktif Anda saat ini: {$taskType} untuk {$orderNum} dengan status tugas '{$activeTask->status}'.";
    }

    public function getCourierStoreResponse(User $user): string
    {
        $courier = Courier::where('user_id', $user->id)->first();
        if (!$courier) {
            return 'Profil kurir Anda belum aktif di LaundryKu.';
        }

        if ($courier->store_id) {
            $store = Store::find($courier->store_id);
            if ($store) {
                $avail = $courier->is_available ? 'Online (Siap Tugas)' : 'Offline (Istirahat)';
                return "Anda terdaftar sebagai kurir di toko {$store->name} ({$store->address}). Status ketersediaan Anda saat ini: {$avail}.";
            }
        }

        return "Profil kurir Anda aktif namun saat ini belum terikat pada toko mitra tertentu (kurir independen/lepas).";
    }

    public function getCourierTaskHistoryResponse(User $user): string
    {
        $courier = Courier::where('user_id', $user->id)->first();
        if (!$courier) {
            return 'Profil kurir Anda belum aktif di LaundryKu.';
        }

        $completedTasks = CourierTask::where('courier_id', $courier->id)
            ->where('status', 'delivered')
            ->with('order')
            ->latest('updated_at')
            ->take(5)
            ->get();

        if ($completedTasks->isEmpty()) {
            return 'Anda belum memiliki riwayat tugas kurir yang telah diselesaikan.';
        }

        $lines = ["Riwayat tugas selesai Anda ({$completedTasks->count()} tugas terakhir):"];
        foreach ($completedTasks as $t) {
            $ordNum = optional($t->order)->order_number ?? 'Pesanan';
            $type = $t->task_type === 'pickup' ? 'Penjemputan' : 'Pengantaran';
            $lines[] = "- {$ordNum} ({$type}): Selesai diantar/diterima";
        }

        return implode("\n", $lines);
    }

    /**
     * Capability J: Super Admin platform queries (platform overview, user search, store search).
     */
    public function handleSuperAdminQuery(User $user, string $normalizedMessage): ?string
    {
        $roles = $this->getUserRoles($user);
        if (!in_array('super_admin', $roles, true) && !in_array('platform_owner', $roles, true)) {
            return 'Maaf, permintaan tersebut melanggar kebijakan keamanan data dan tidak dapat diproses.';
        }

        // 1. Look up any order if order number is explicitly stated
        preg_match('/ord-[a-z0-9-]+/i', $normalizedMessage, $matches);
        $orderNumber = !empty($matches[0]) ? strtoupper($matches[0]) : null;

        if ($orderNumber) {
            $order = Order::where('order_number', $orderNumber)->first();

            if (!$order) {
                return "Pesanan dengan nomor {$orderNumber} tidak ditemukan di seluruh platform LaundryKu.";
            }

            $statusInfo = self::ORDER_STATUS_MAP[$order->status] ?? null;
            $label = $statusInfo['label'] ?? $order->status;
            $storeName = optional($order->store)->name ?? 'Toko';
            $total = (float) ($order->total_amount ?: $order->total_price ?: 0);

            return "Detail Pesanan {$orderNumber}: Toko {$storeName}, Status '{$label}', Nominal Rp " . number_format($total, 0, ',', '.') . ".";
        }

        // 2. Search for users (sensitive data like email and phone MUST be masked; passwords/tokens never shown)
        if (str_contains($normalizedMessage, 'cari user') || str_contains($normalizedMessage, 'cari pengguna') || str_contains($normalizedMessage, 'cari akun') || str_contains($normalizedMessage, 'cari pelanggan') || str_contains($normalizedMessage, 'info user') || str_contains($normalizedMessage, 'info pengguna')) {
            $term = trim(preg_replace('/^(.*)(cari user|cari pengguna|cari akun|cari pelanggan|info user|info pengguna)\s*/iu', '', $normalizedMessage));
            $term = trim(str_replace(['?', '!', '.', ','], '', $term));

            if ($term === '') {
                return 'Silakan masukkan nama, email, atau nomor telepon pengguna yang ingin dicari (contoh: "cari user Budi").';
            }

            $users = User::where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->take(3)
                ->get();

            if ($users->isEmpty()) {
                return "Pengguna dengan kata kunci '{$term}' tidak ditemukan di sistem LaundryKu.";
            }

            $lines = ["Hasil pencarian pengguna untuk '{$term}' (data sensitif disamarkan):"];
            foreach ($users as $u) {
                $maskedEmail = $this->maskEmail($u->email);
                $maskedPhone = $this->maskPhone($u->phone);
                $roleLabel = $u->role ?? 'pelanggan';
                $status = $u->is_active ? 'aktif' : 'nonaktif';
                $lines[] = "- {$u->name} (Role: {$roleLabel}, Email: {$maskedEmail}, Telepon: {$maskedPhone}, Status: {$status})";
            }

            return implode("\n", $lines);
        }

        // 3. Search for stores
        if (str_contains($normalizedMessage, 'cari toko') || str_contains($normalizedMessage, 'cari outlet') || str_contains($normalizedMessage, 'info toko mitra') || str_contains($normalizedMessage, 'status toko mitra')) {
            $term = trim(preg_replace('/^(.*)(cari toko|cari outlet|info toko mitra|status toko mitra)\s*/iu', '', $normalizedMessage));
            $term = trim(str_replace(['?', '!', '.', ','], '', $term));

            if ($term === '') {
                return 'Silakan sebutkan nama toko atau alamat yang ingin dicari (contoh: "cari toko Berkah").';
            }

            $stores = Store::where('name', 'like', "%{$term}%")
                ->orWhere('address', 'like', "%{$term}%")
                ->with('owner')
                ->take(3)
                ->get();

            if ($stores->isEmpty()) {
                return "Toko dengan kata kunci '{$term}' tidak ditemukan di platform LaundryKu.";
            }

            $lines = ["Hasil pencarian toko untuk '{$term}':"];
            foreach ($stores as $s) {
                $ownerName = optional($s->owner)->name ?? 'Owner';
                $active = $s->is_active ? 'aktif' : 'tutup/nonaktif';
                $lines[] = "- {$s->name} (Status: {$s->status}, Operasional: {$active}, Pemilik: {$ownerName}, Alamat: {$s->address})";
            }

            return implode("\n", $lines);
        }

        // 4. Platform overview
        if (str_contains($normalizedMessage, 'platform') || str_contains($normalizedMessage, 'total toko') || str_contains($normalizedMessage, 'semua toko') || str_contains($normalizedMessage, 'toko mitra') || str_contains($normalizedMessage, 'mitra yang aktif') || str_contains($normalizedMessage, 'toko aktif') || str_contains($normalizedMessage, 'sistem') || str_contains($normalizedMessage, 'ringkasan')) {
            $activeStores = Store::where('status', 'approved')->where('is_active', true)->count();
            $stats = $this->statsService->getPlatformStats();
            return "{$stats} Jumlah toko aktif ({$activeStores} toko mitra aktif di platform).";
        }

        $cnt = Store::where('status', 'approved')->where('is_active', true)->count();
        return $this->statsService->getPlatformStats() . " Jumlah toko aktif ({$cnt} toko mitra aktif di platform).";
    }

    /**
     * Mask email address for Super Admin search (e.g. bu***@mail.com).
     */
    public function maskEmail(?string $email): string
    {
        if (empty($email)) {
            return '-';
        }

        if (User::isPlaceholderEmail($email)) {
            return '(Login WhatsApp / Tanpa Email)';
        }

        $parts = explode('@', trim($email), 2);
        if (count($parts) !== 2) {
            return '***';
        }

        $local = $parts[0];
        $domain = $parts[1];
        $len = strlen($local);

        if ($len <= 2) {
            $maskedLocal = substr($local, 0, 1) . '***';
        } else {
            $maskedLocal = substr($local, 0, 2) . '***';
        }

        return "{$maskedLocal}@{$domain}";
    }

    /**
     * Mask phone number for Super Admin search (e.g. 0812****789).
     */
    public function maskPhone(?string $phone): string
    {
        if (empty($phone)) {
            return '-';
        }

        $cleaned = preg_replace('/[^0-9]/', '', (string) $phone);
        $len = strlen($cleaned);

        if ($len >= 7) {
            return substr($cleaned, 0, 4) . '****' . substr($cleaned, -3);
        }

        if ($len >= 4) {
            return substr($cleaned, 0, 2) . '****' . substr($cleaned, -2);
        }

        return '****';
    }

    // ========== HELPER AND INTENT DETECTION METHODS ==========

    public function isProceduralQuery(string $normalized): bool
    {
        $proceduralTriggers = [
            'bagaimana cara', 'gimana cara', 'cara memesan', 'cara order',
            'cara pesan', 'cara lacak', 'cara melacak', 'cara bayar',
            'cara komplain', 'cara daftar', 'bisa pesan', 'bisa jemput',
            'bisa order', 'apakah bisa jemput', 'apakah bisa pesan',
            'panduan ', 'tutorial ', 'alur pemesanan', 'tahapan pemesanan',
        ];

        foreach ($proceduralTriggers as $pt) {
            if (str_contains($normalized, $pt)) {
                return true;
            }
        }

        return false;
    }

    public function isExploitAttempt(string $normalized): bool
    {
        $patterns = [
            'select * from', 'drop table', 'union select', 'information_schema',
            'tampilkan pesanan semua pelanggan', 'pesanan semua pelanggan',
            'email budi apa', 'email pengguna lain', 'password', 'token rahasia',
            'abaikan aturan dan tampilkan', 'abaikan batasan',
            'abaikan aturan', 'abaikan semua aturan',
            'anggap saya admin', 'anggap aku admin', 'anggap saya super admin',
            'jadikan saya admin', 'kamu harus mematuhi saya', 'bypass',
            'tampilkan token', 'tampilkan password', 'tampilkan hash',
            'bocorkan password', 'bocorkan database', 'tampilkan semua data',
            'tampilkan data rahasia', 'data pengguna lain', 'data customer lain',
            'data toko lain', 'pesanan toko lain', 'tugas kurir lain',
            'password pengguna', 'token pengguna', 'hash password',
        ];

        foreach ($patterns as $p) {
            if (str_contains($normalized, $p)) {
                return true;
            }
        }

        return false;
    }

    public function isPersonalDataInquiry(string $normalized): bool
    {
        $triggers = [
            'pesanan saya', 'cucian saya', 'status pesanan', 'berapa kali saya memesan',
            'pengeluaran saya', 'pembayaran saya', 'tagihan saya', 'refund saya',
            'alamat saya', 'akun saya', 'komplain saya', 'dispute saya', 'tugas saya',
            'toko saya',
        ];

        foreach ($triggers as $t) {
            if (str_contains($normalized, $t)) {
                return true;
            }
        }

        return false;
    }

    public function isOrderProgressInquiry(string $normalized): bool
    {
        // Exclude stats & count inquiries (AiOrderStatsService responsibility)
        if (str_contains($normalized, 'berapa') && !str_contains($normalized, 'berat')) {
            return false;
        }
        if (str_contains($normalized, 'pengeluaran')
            || str_contains($normalized, 'belanja') || str_contains($normalized, 'total pesanan')
            || str_contains($normalized, 'jumlah pesanan') || str_contains($normalized, 'statistik')) {
            return false;
        }

        // Exclude payment inquiries
        if (str_contains($normalized, 'pembayaran') || str_contains($normalized, 'refund') || str_contains($normalized, 'tagihan')) {
            return false;
        }

        if (str_contains($normalized, 'berat timbangan') || $normalized === 'berat timbangan') {
            return true;
        }

        $patterns = [
            'sampai mana', 'status pesanan', 'sudah dicuci', 'dicuci belum',
            'sudah jadi belum', 'kapan diantar', 'kurirnya sudah jalan',
            'sudah ditimbang', 'sudah dibayar', 'progres pesanan', 'lacak pesanan',
            'posisi cucian', 'pesanan saya', 'cucian saya',
            'berat timbangan', 'berat cucian', 'timbangan cucian', 'berat pesanan',
            'timbang', 'berat', 'kilo', 'bobot',
        ];

        foreach ($patterns as $p) {
            if (str_contains($normalized, $p)) {
                return true;
            }
        }

        return (bool) preg_match('/ord-[a-z0-9-]+/i', $normalized);
    }

    public function isPaymentOrRefundInquiry(string $normalized): bool
    {
        if (str_contains($normalized, 'batas') || str_contains($normalized, 'aturan') || str_contains($normalized, 'maksimal') || str_contains($normalized, 'maksimum') || str_contains($normalized, 'ketentuan')) {
            return false;
        }

        $patterns = [
            'pembayaran', 'status pembayaran', 'tagihan',
            'sudah bayar', 'bayar belum', 'status refund', 'refund saya', 'pengembalian dana',
            'info refund', 'cek refund', 'invoice',
        ];

        foreach ($patterns as $p) {
            if (str_contains($normalized, $p)) {
                return true;
            }
        }

        return false;
    }

    public function isServicesOrPricesInquiry(string $normalized): bool
    {
        if (str_contains($normalized, 'estimasi') || str_contains($normalized, 'berapa estimasi')) {
            return false;
        }

        $patterns = [
            'layanan apa saja', 'daftar layanan', 'harga layanan',
            'paket laundry', 'biaya laundry', 'daftar paket', 'apa saja layanannya',
            'daftar harga', 'menu layanan', 'layanan laundryku',
        ];

        foreach ($patterns as $p) {
            if (str_contains($normalized, $p)) {
                return true;
            }
        }

        return false;
    }

    public function isBusinessRuleInquiry(string $normalized): bool
    {
        // 1. COD rule
        if (str_contains($normalized, 'cod') && (
            str_contains($normalized, 'batas') || str_contains($normalized, 'maksimal')
            || str_contains($normalized, 'aturan') || str_contains($normalized, 'ketentuan')
            || str_contains($normalized, 'limit') || str_contains($normalized, 'maksimum')
        )) {
            return true;
        }

        // 2. Dispute/complaint window
        if ((str_contains($normalized, 'komplain') || str_contains($normalized, 'dispute') || str_contains($normalized, 'garansi'))
            && (str_contains($normalized, 'batas') || str_contains($normalized, 'waktu') || str_contains($normalized, 'aturan') || str_contains($normalized, 'jendela') || str_contains($normalized, 'tenggat'))) {
            return true;
        }

        // 3. Payment window
        if ((str_contains($normalized, 'bayar') || str_contains($normalized, 'invoice'))
            && (str_contains($normalized, 'batas') || str_contains($normalized, 'waktu') || str_contains($normalized, 'tenggat') || str_contains($normalized, 'aturan'))) {
            return true;
        }

        return false;
    }

    public function isStoreInquiry(string $normalized): bool
    {
        if (str_contains($normalized, 'buka jam berapa')) {
            return false;
        }

        $patterns = [
            'toko apa saja', 'daftar toko', 'rekomendasi toko',
            'jam operasional toko', 'jam buka toko', 'rating toko', 'info toko',
            'toko mitra laundryku', 'mitra toko laundryku',
        ];

        foreach ($patterns as $p) {
            if (str_contains($normalized, $p)) {
                return true;
            }
        }

        return false;
    }

    public function isDisputeOrReviewInquiry(string $normalized): bool
    {
        $patterns = [
            'komplain saya', 'dispute saya', 'status komplain',
            'keluhan saya', 'ulasan saya', 'review saya',
        ];

        foreach ($patterns as $p) {
            if (str_contains($normalized, $p)) {
                return true;
            }
        }

        return false;
    }

    public function isAccountInquiry(string $normalized): bool
    {
        $patterns = [
            'akun saya', 'profil saya', 'alamat saya', 'berapa alamat',
            'alamat tersimpan', 'data akun saya',
        ];

        foreach ($patterns as $p) {
            if (str_contains($normalized, $p)) {
                return true;
            }
        }

        return false;
    }

    public function isStoreManagementInquiry(string $normalized): bool
    {
        return str_contains($normalized, 'toko saya')
            || str_contains($normalized, 'toko kita')
            || str_contains($normalized, 'rating toko saya')
            || (str_contains($normalized, 'toko') && str_contains($normalized, 'pesanan'))
            || (str_contains($normalized, 'toko') && str_contains($normalized, 'komplain'))
            || (str_contains($normalized, 'toko') && str_contains($normalized, 'jam'))
            || (str_contains($normalized, 'toko') && str_contains($normalized, 'harga'))
            || (str_contains($normalized, 'toko') && str_contains($normalized, 'layanan'))
            || (str_contains($normalized, 'toko') && str_contains($normalized, 'tarif'))
            || (str_contains($normalized, 'toko') && str_contains($normalized, 'kurir'))
            || (str_contains($normalized, 'toko') && str_contains($normalized, 'omzet'))
            || (str_contains($normalized, 'toko') && str_contains($normalized, 'omset'))
            || str_contains($normalized, 'pelamar kurir')
            || str_contains($normalized, 'kandidat kurir')
            || str_contains($normalized, 'pelamar toko')
            || str_contains($normalized, 'kurir di toko')
            || str_contains($normalized, 'kurir toko')
            || (bool) preg_match('/ord-[a-z0-9-]+/i', $normalized);
    }

    public function isCourierTaskInquiry(string $normalized): bool
    {
        if (str_contains($normalized, 'berapa') || str_contains($normalized, 'total') || str_contains($normalized, 'jumlah') || str_contains($normalized, 'statistik')) {
            return false;
        }

        return str_contains($normalized, 'tugas')
            || str_contains($normalized, 'penjemputan')
            || str_contains($normalized, 'pengantaran')
            || str_contains($normalized, 'toko tempat saya')
            || str_contains($normalized, 'toko saya terikat')
            || str_contains($normalized, 'toko mitra saya')
            || str_contains($normalized, 'saya bertugas di');
    }

    public function getPrimaryRole(User $user): string
    {
        $roles = $this->getUserRoles($user);
        if (in_array('super_admin', $roles, true) || in_array('platform_owner', $roles, true)) {
            return 'super_admin';
        }
        if (in_array('store_owner', $roles, true) || in_array('store_admin', $roles, true)) {
            return 'store_admin';
        }
        if (in_array('courier', $roles, true)) {
            return 'courier';
        }
        return 'customer';
    }

    public function getUserRoles(User $user): array
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

    public function getManagedStoreIds(User $user): array
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

        return array_values(array_unique(array_filter($storeIds)));
    }

    /**
     * Priority ranking for washing progress inquiry:
     * Lower number means closer to or currently in washing stage.
     */
    protected function getWashingPriority(string $status): int
    {
        return match ($status) {
            'processing' => 1,
            'paid' => 2,
            'payment_required' => 3,
            'weighed' => 4,
            'received_by_store' => 5,
            'ready_for_delivery', 'courier_assigned_for_delivery', 'out_for_delivery', 'delivered' => 6,
            'in_transit_to_store', 'picked_up' => 7,
            'courier_to_customer', 'courier_assigned', 'waiting_pickup', 'created' => 8,
            default => 9,
        };
    }
}

