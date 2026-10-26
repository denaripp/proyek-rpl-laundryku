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

        // 1. Top customers (nama saja, tanpa kontak)
        if (str_contains($normalizedMessage, 'top customer') || str_contains($normalizedMessage, 'paling sering') || str_contains($normalizedMessage, 'paling loyal') || str_contains($normalizedMessage, 'pelanggan teratas') || str_contains($normalizedMessage, 'pelanggan paling loyal')) {
            return $this->getStoreTopCustomersResponse($user);
        }

        // 2. Best-selling services
        if (str_contains($normalizedMessage, 'terlaris') || str_contains($normalizedMessage, 'paling laku') || str_contains($normalizedMessage, 'paling laris') || str_contains($normalizedMessage, 'bestseller') || str_contains($normalizedMessage, 'paling banyak dipesan')) {
            return $this->getStoreBestsellersResponse($user);
        }

        // 3. Operational settings, radius, address, approval status
        if (str_contains($normalizedMessage, 'radius') || str_contains($normalizedMessage, 'jangkauan') || str_contains($normalizedMessage, 'approval') || str_contains($normalizedMessage, 'disetujui admin') || str_contains($normalizedMessage, 'status persetujuan') || str_contains($normalizedMessage, 'status approval') || str_contains($normalizedMessage, 'terdaftar di mana') || str_contains($normalizedMessage, 'tutup gak') || str_contains($normalizedMessage, 'minggu tutup') || (str_contains($normalizedMessage, 'alamat') && str_contains($normalizedMessage, 'toko'))) {
            return $this->getStoreSettingsResponse($user, $normalizedMessage);
        }

        // 4. Action orders & status breakdown
        if (str_contains($normalizedMessage, 'belum ditimbang') || str_contains($normalizedMessage, 'blm ditimbang') || str_contains($normalizedMessage, 'siap diantar') || str_contains($normalizedMessage, 'butuh tindakan') || str_contains($normalizedMessage, 'perlu tindakan') || str_contains($normalizedMessage, 'perlu diproses') || str_contains($normalizedMessage, 'perlu proses') || str_contains($normalizedMessage, 'lagi dicuci') || str_contains($normalizedMessage, 'orderan baru') || str_contains($normalizedMessage, 'pesanan baru') || str_contains($normalizedMessage, 'belum kelar') || str_contains($normalizedMessage, 'blm kelar') || str_contains($normalizedMessage, 'msh diproses') || str_contains($normalizedMessage, 'masih diproses') || str_contains($normalizedMessage, 'nunggu jemput') || str_contains($normalizedMessage, 'menunggu penjemputan') || str_contains($normalizedMessage, 'status orderan toko') || str_contains($normalizedMessage, 'status pesanan toko saat ini')) {
            return $this->getStoreActionOrdersResponse($user, $normalizedMessage);
        }

        // 5. Check if query is asking for applicants / courier candidates
        if (str_contains($normalizedMessage, 'pelamar') || str_contains($normalizedMessage, 'kandidat') || str_contains($normalizedMessage, 'rekrut')) {
            return $this->getStoreApplicantsResponse($user);
        }

        // 6. Check if query is asking for store couriers
        if (str_contains($normalizedMessage, 'kurir') && !str_contains($normalizedMessage, 'tugas')) {
            return $this->getStoreCouriersResponse($user);
        }

        // 7. Check if query is asking for store omzet / revenue
        if (str_contains($normalizedMessage, 'omset') || str_contains($normalizedMessage, 'omzet') || str_contains($normalizedMessage, 'pendapatan') || str_contains($normalizedMessage, 'pemasukan') || str_contains($normalizedMessage, 'duit masuk') || str_contains($normalizedMessage, 'uang masuk')) {
            return $this->getStoreRevenueResponse($user, $normalizedMessage);
        }

        // 8. Check if query is asking for store services & prices
        if (str_contains($normalizedMessage, 'harga') || str_contains($normalizedMessage, 'layanan') || str_contains($normalizedMessage, 'tarif') || str_contains($normalizedMessage, 'menu servis') || str_contains($normalizedMessage, 'list harga')) {
            return $this->getStoreServicesResponse($user);
        }

        // 9. Check if query is asking for store complaints
        if (str_contains($normalizedMessage, 'komplain') || str_contains($normalizedMessage, 'keluhan') || str_contains($normalizedMessage, 'dispute')) {
            return $this->getStoreComplaintsResponse($user);
        }

        // 10. Check if query is asking for store operational hours
        if (str_contains($normalizedMessage, 'jam') || str_contains($normalizedMessage, 'buka') || str_contains($normalizedMessage, 'operasional')) {
            return $this->getStoreHoursResponse($user);
        }

        // 11. Check if query is asking for store rating & reviews
        if (str_contains($normalizedMessage, 'rating') || str_contains($normalizedMessage, 'bintang') || str_contains($normalizedMessage, 'ulasan toko') || str_contains($normalizedMessage, 'ulasan') || str_contains($normalizedMessage, 'review')) {
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
            return "Toko yang Anda kelola ({$storeNames}) belum mencatatkan transaksi terbayar hari ini. Saran tindakan: Anda dapat mempromosikan layanan toko atau memastikan jam operasional toko aktif di /store/settings.";
        }

        $storageTimezone = config('app.timezone', 'UTC');
        $startOfTodayStorage = Carbon::now('Asia/Jakarta')->startOfDay()->setTimezone($storageTimezone);
        $endOfTodayStorage = Carbon::now('Asia/Jakarta')->endOfDay()->setTimezone($storageTimezone);

        $startOfYesterdayStorage = Carbon::now('Asia/Jakarta')->subDay()->startOfDay()->setTimezone($storageTimezone);
        $endOfYesterdayStorage = Carbon::now('Asia/Jakarta')->subDay()->endOfDay()->setTimezone($storageTimezone);

        $startOfWeekStorage = Carbon::now('Asia/Jakarta')->startOfWeek()->setTimezone($storageTimezone);
        $startOfMonthStorage = Carbon::now('Asia/Jakarta')->startOfMonth()->setTimezone($storageTimezone);

        $orderIds = (clone $query)->pluck('id');
        $hasPayments = Payment::whereIn('order_id', $orderIds)->where('status', 'paid')->exists();

        if ($hasPayments) {
            $todayRevenue = (float) Payment::where('status', 'paid')
                ->whereIn('order_id', $orderIds)
                ->whereBetween('paid_at', [$startOfTodayStorage, $endOfTodayStorage])
                ->sum('amount');

            $yesterdayRevenue = (float) Payment::where('status', 'paid')
                ->whereIn('order_id', $orderIds)
                ->whereBetween('paid_at', [$startOfYesterdayStorage, $endOfYesterdayStorage])
                ->sum('amount');

            $weekRevenue = (float) Payment::where('status', 'paid')
                ->whereIn('order_id', $orderIds)
                ->whereBetween('paid_at', [$startOfWeekStorage, $endOfTodayStorage])
                ->sum('amount');

            $monthRevenue = (float) Payment::where('status', 'paid')
                ->whereIn('order_id', $orderIds)
                ->whereBetween('paid_at', [$startOfMonthStorage, $endOfTodayStorage])
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

            $yesterdayRevenue = (float) (clone $paidQuery)
                ->where(function ($q) use ($startOfYesterdayStorage, $endOfYesterdayStorage) {
                    $q->whereBetween('paid_at', [$startOfYesterdayStorage, $endOfYesterdayStorage])
                      ->orWhere(function ($sub) use ($startOfYesterdayStorage, $endOfYesterdayStorage) {
                          $sub->whereNull('paid_at')
                              ->whereBetween('created_at', [$startOfYesterdayStorage, $endOfYesterdayStorage]);
                      });
                })
                ->sum(DB::raw('COALESCE(total_amount, total_price, 0)'));

            $weekRevenue = (float) (clone $paidQuery)
                ->where(function ($q) use ($startOfWeekStorage, $endOfTodayStorage) {
                    $q->whereBetween('paid_at', [$startOfWeekStorage, $endOfTodayStorage])
                      ->orWhere(function ($sub) use ($startOfWeekStorage, $endOfTodayStorage) {
                          $sub->whereNull('paid_at')
                              ->whereBetween('created_at', [$startOfWeekStorage, $endOfTodayStorage]);
                      });
                })
                ->sum(DB::raw('COALESCE(total_amount, total_price, 0)'));

            $monthRevenue = (float) (clone $paidQuery)
                ->where(function ($q) use ($startOfMonthStorage, $endOfTodayStorage) {
                    $q->whereBetween('paid_at', [$startOfMonthStorage, $endOfTodayStorage])
                      ->orWhere(function ($sub) use ($startOfMonthStorage, $endOfTodayStorage) {
                          $sub->whereNull('paid_at')
                              ->whereBetween('created_at', [$startOfMonthStorage, $endOfTodayStorage]);
                      });
                })
                ->sum(DB::raw('COALESCE(total_amount, total_price, 0)'));
        }

        $todayFmt = 'Rp ' . number_format($todayRevenue, 0, ',', '.');
        $yesterdayFmt = 'Rp ' . number_format($yesterdayRevenue, 0, ',', '.');
        $weekFmt = 'Rp ' . number_format($weekRevenue, 0, ',', '.');
        $monthFmt = 'Rp ' . number_format($monthRevenue, 0, ',', '.');
        $totalFmt = 'Rp ' . number_format($totalRevenue, 0, ',', '.');

        $norm = strtolower($message);

        // Comparison query: "hari ini dibanding kemarin"
        if (str_contains($norm, 'dibanding') || str_contains($norm, 'perbandingan') || (str_contains($norm, 'hari ini') && str_contains($norm, 'kemarin'))) {
            $diff = abs($todayRevenue - $yesterdayRevenue);
            $diffFmt = 'Rp ' . number_format($diff, 0, ',', '.');
            $compStr = $todayRevenue >= $yesterdayRevenue
                ? "Pemasukan hari ini lebih tinggi {$diffFmt} dibanding kemarin."
                : "Pemasukan hari ini lebih rendah {$diffFmt} dibanding kemarin.";

            return "Perbandingan omzet toko {$storeNames} (hanya transaksi terbayar):\n- Hari ini: {$todayFmt}\n- Kemarin: {$yesterdayFmt}\n{$compStr}";
        }

        if (str_contains($norm, 'kemarin')) {
            return "Omzet kemarin untuk toko {$storeNames} adalah {$yesterdayFmt} (hanya transaksi yang telah terbayar).";
        }

        if (str_contains($norm, 'minggu ini') || str_contains($norm, 'pekan ini')) {
            return "Total pendapatan toko {$storeNames} minggu ini adalah {$weekFmt} (hanya transaksi yang telah terbayar).";
        }

        if (str_contains($norm, 'bulan ini')) {
            return "Total omzet toko {$storeNames} bulan ini adalah {$monthFmt} (hanya transaksi yang telah terbayar). Total keseluruhan: {$totalFmt}.";
        }

        if (str_contains($norm, 'hari ini')) {
            return "Omzet hari ini untuk toko {$storeNames} adalah {$todayFmt} (hanya transaksi yang telah terbayar). Total omzet keseluruhan: {$totalFmt}.";
        }

        return "Ringkasan omzet toko {$storeNames} (hanya transaksi terbayar):\n- Hari ini: {$todayFmt}\n- Kemarin: {$yesterdayFmt}\n- Minggu ini: {$weekFmt}\n- Bulan ini: {$monthFmt}\n- Total omzet: {$totalFmt}";
    }

    public function getStoreActionOrdersResponse(User $user, string $message = ''): string
    {
        $storeIds = $this->getManagedStoreIds($user);
        if (empty($storeIds)) {
            return 'Anda belum memiliki toko mitra yang dikelola.';
        }

        $storeNames = Store::whereIn('id', $storeIds)->pluck('name')->implode(', ');
        $query = Order::whereIn('store_id', $storeIds);

        // Belum ditimbang: actual_weight null or 0, in active pre-weighed or receiving states
        $unweighed = (clone $query)->where(function ($q) {
            $q->whereNull('actual_weight')->orWhere('actual_weight', 0);
        })->whereIn('status', [
            'created', 'waiting_pickup', 'courier_assigned', 'courier_to_customer',
            'picked_up', 'in_transit_to_store', 'received_by_store',
        ])->count();

        // Menunggu penjemputan
        $waitingPickup = (clone $query)->whereIn('status', [
            'created', 'waiting_pickup',
        ])->count();

        // Siap diantar
        $readyDelivery = (clone $query)->where('status', 'ready_for_delivery')->count();

        // Sedang dicuci / diproses
        $inProcess = (clone $query)->where('status', 'processing')->count();

        // Orderan baru masuk hari ini
        $storageTimezone = config('app.timezone', 'UTC');
        $startOfTodayStorage = Carbon::now('Asia/Jakarta')->startOfDay()->setTimezone($storageTimezone);
        $endOfTodayStorage = Carbon::now('Asia/Jakarta')->endOfDay()->setTimezone($storageTimezone);

        $newToday = (clone $query)
            ->whereRaw('COALESCE(order_date, created_at) >= ? AND COALESCE(order_date, created_at) <= ?', [
                $startOfTodayStorage->format('Y-m-d H:i:s'),
                $endOfTodayStorage->format('Y-m-d H:i:s'),
            ])
            ->count();

        // Completed today
        $completedToday = (clone $query)
            ->where('status', 'completed')
            ->whereRaw('updated_at >= ? AND updated_at <= ?', [
                $startOfTodayStorage->format('Y-m-d H:i:s'),
                $endOfTodayStorage->format('Y-m-d H:i:s'),
            ])
            ->count();

        $norm = strtolower($message);

        if (str_contains($norm, 'timbang')) {
            if ($unweighed === 0) {
                return "Semua cucian yang tiba di toko {$storeNames} sudah ditimbang. Tidak ada antrean penimbangan saat ini.";
            }
            return "Toko {$storeNames} memiliki {$unweighed} pesanan yang belum ditimbang. Silakan timbang pakaian fisik dan masukkan angka timbangan di menu Pesanan Toko (/store/orders).";
        }

        if (str_contains($norm, 'siap diantar') || str_contains($norm, 'siap antar')) {
            if ($readyDelivery === 0) {
                return "Saat ini belum ada pesanan yang siap diantar di toko {$storeNames}.";
            }
            return "Toko {$storeNames} memiliki {$readyDelivery} pesanan yang sudah selesai dikerjakan dan siap diantar oleh kurir.";
        }

        if (str_contains($norm, 'jemput')) {
            if ($waitingPickup === 0) {
                return "Saat ini tidak ada pesanan yang menunggu penjemputan kurir untuk toko {$storeNames}.";
            }
            return "Toko {$storeNames} memiliki {$waitingPickup} pesanan yang sedang menunggu penjemputan oleh kurir.";
        }

        if (str_contains($norm, 'dicuci') || str_contains($norm, 'diproses')) {
            if ($inProcess === 0) {
                return "Saat ini tidak ada cucian yang sedang dalam tahap pencucian di toko {$storeNames}.";
            }
            return "Toko {$storeNames} memiliki {$inProcess} pesanan cucian yang sedang diproses/dicuci.";
        }

        if (str_contains($norm, 'baru') || str_contains($norm, 'orderan baru')) {
            return "Hari ini ada {$newToday} pesanan baru yang masuk di toko {$storeNames}.";
        }

        if (str_contains($norm, 'selesai') && str_contains($norm, 'hari ini')) {
            return "Hari ini ada {$completedToday} pesanan yang telah selesai di toko {$storeNames}.";
        }

        return "Ringkasan status operasional pesanan di toko {$storeNames}:\n"
            . "- Belum ditimbang: {$unweighed} pesanan\n"
            . "- Menunggu penjemputan kurir: {$waitingPickup} pesanan\n"
            . "- Sedang dicuci / diproses: {$inProcess} pesanan\n"
            . "- Siap diantar ke pelanggan: {$readyDelivery} pesanan\n"
            . "- Pesanan baru hari ini: {$newToday} pesanan.\n"
            . "Kelola rincian aksi pada menu Pesanan Toko (/store/orders).";
    }

    public function getStoreTopCustomersResponse(User $user): string
    {
        $storeIds = $this->getManagedStoreIds($user);
        if (empty($storeIds)) {
            return 'Anda belum memiliki toko mitra yang dikelola.';
        }

        $storeNames = Store::whereIn('id', $storeIds)->pluck('name')->implode(', ');

        $top = Order::whereIn('store_id', $storeIds)
            ->whereNotNull('customer_id')
            ->select('customer_id', DB::raw('count(*) as order_count'))
            ->groupBy('customer_id')
            ->orderByDesc('order_count')
            ->with('customer')
            ->take(3)
            ->get();

        if ($top->isEmpty()) {
            return "Toko {$storeNames} belum memiliki data riwayat pesanan dari pelanggan.";
        }

        $lines = ["Pelanggan teratas di toko {$storeNames} berdasarkan frekuensi pesanan:"];
        $rank = 1;
        foreach ($top as $item) {
            $name = optional($item->customer)->name ?? 'Pelanggan Setia';
            $lines[] = "{$rank}. {$name} ({$item->order_count} kali mencuci)";
            $rank++;
        }

        return implode("\n", $lines);
    }

    public function getStoreBestsellersResponse(User $user): string
    {
        $storeIds = $this->getManagedStoreIds($user);
        if (empty($storeIds)) {
            return 'Anda belum memiliki toko mitra yang dikelola.';
        }

        $storeNames = Store::whereIn('id', $storeIds)->pluck('name')->implode(', ');

        $best = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('services', 'order_items.service_id', '=', 'services.id')
            ->whereIn('orders.store_id', $storeIds)
            ->select('services.name as service_name', DB::raw('SUM(order_items.quantity) as total_qty'), DB::raw('COUNT(order_items.id) as order_count'))
            ->groupBy('services.name')
            ->orderByDesc('total_qty')
            ->take(3)
            ->get();

        if ($best->isEmpty()) {
            return "Toko {$storeNames} belum mencatatkan transaksi layanan. Layanan terpopuler pada platform yang dapat Anda tawarkan adalah Cuci Komplit Kiloan dan Dry Clean.";
        }

        $lines = ["Layanan terlaris di toko {$storeNames}:"];
        $rank = 1;
        foreach ($best as $item) {
            $lines[] = "{$rank}. {$item->service_name} (total {$item->total_qty} unit dipesan dalam {$item->order_count} pesanan)";
            $rank++;
        }

        return implode("\n", $lines);
    }

    public function getStoreSettingsResponse(User $user, string $message = ''): string
    {
        $storeIds = $this->getManagedStoreIds($user);
        if (empty($storeIds)) {
            return 'Anda belum memiliki toko mitra yang dikelola.';
        }

        $store = Store::whereIn('id', $storeIds)->first();
        if (!$store) {
            return 'Data toko Anda tidak ditemukan.';
        }

        $norm = strtolower($message);

        // Check if query is specifically asking about radius
        if (str_contains($norm, 'radius') || str_contains($norm, 'jangkauan')) {
            $radius = $store->service_radius_km ? number_format((float) $store->service_radius_km, 2) : '5.00';
            return "Radius jangkauan layanan toko {$store->name} adalah {$radius} km. Anda dapat memperbarui radius jangkauan layanan melalui Dashboard Mitra di Pengaturan Toko (/store/settings).";
        }

        // Check if query is specifically asking about approval status
        if (str_contains($norm, 'approval') || str_contains($norm, 'disetujui') || str_contains($norm, 'status persetujuan') || str_contains($norm, 'status gerai') || str_contains($norm, 'status approval')) {
            $statusLabel = match($store->status) {
                'approved' => 'Disetujui (Approved)',
                'pending' => 'Menunggu Persetujuan Admin (Pending)',
                'rejected' => 'Ditolak (Rejected)',
                default => ucfirst($store->status ?? 'pending'),
            };
            $activeLabel = $store->is_active ? 'Aktif' : 'Nonaktif / Tutup Sementara';
            return "Status toko {$store->name}: Status pendaftaran '{$statusLabel}', Status operasional '{$activeLabel}'.";
        }

        // Check if query is specifically asking about store address
        if (str_contains($norm, 'alamat') || str_contains($norm, 'lokasi') || str_contains($norm, 'terdaftar')) {
            $cityProv = array_filter([$store->city, $store->province]);
            $cityProvStr = !empty($cityProv) ? ' (' . implode(', ', $cityProv) . ')' : '';
            return "Alamat toko {$store->name} terdaftar di: {$store->address}{$cityProvStr}. Anda dapat memperbarui lokasi di /store/settings.";
        }

        // General settings overview
        $radius = $store->service_radius_km ? number_format((float) $store->service_radius_km, 2) : '5.00';
        $statusLabel = match($store->status) {
            'approved' => 'Disetujui',
            'pending' => 'Menunggu Persetujuan',
            'rejected' => 'Ditolak',
            default => ucfirst($store->status ?? 'pending'),
        };
        $cityProv = array_filter([$store->city, $store->province]);
        $cityProvStr = !empty($cityProv) ? ' (' . implode(', ', $cityProv) . ')' : '';

        return "Pengaturan gerai {$store->name}:\n- Alamat: {$store->address}{$cityProvStr}\n- Radius layanan: {$radius} km\n- Status toko: {$statusLabel} (Operasional: " . ($store->is_active ? 'Aktif' : 'Nonaktif') . ")\nPengaturan dapat dikelola di /store/settings dan profil akun di /store/profile.";
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

    public function getStoreHoursResponse(User $user, string $message = ''): string
    {
        $storeIds = $this->getManagedStoreIds($user);
        if (empty($storeIds)) {
            return 'Anda belum memiliki toko mitra yang dikelola.';
        }

        $store = Store::whereIn('id', $storeIds)->first();
        if (!$store) {
            return 'Data toko Anda tidak ditemukan.';
        }

        $rawHours = $store->operational_hours;
        if (is_string($rawHours)) {
            $decoded = json_decode($rawHours, true);
            if (is_array($decoded)) {
                $rawHours = $decoded;
            }
        }

        $norm = strtolower($message);

        if (is_array($rawHours)) {
            $days = [
                'monday' => 'Senin', 'tuesday' => 'Selasa', 'wednesday' => 'Rabu',
                'thursday' => 'Kamis', 'friday' => 'Jumat', 'saturday' => 'Sabtu', 'sunday' => 'Minggu',
            ];

            if (str_contains($norm, 'minggu')) {
                $sun = $rawHours['sunday'] ?? null;
                if (!empty($sun['closed'])) {
                    return "Hari Minggu toko {$store->name} libur / tutup.";
                } elseif (!empty($sun['open']) && !empty($sun['close'])) {
                    return "Hari Minggu toko {$store->name} buka dari jam {$sun['open']} sampai {$sun['close']} WIB.";
                }
            }

            $formatted = [];
            foreach ($days as $key => $label) {
                if (!empty($rawHours[$key]['closed'])) {
                    $formatted[] = "{$label}: Libur";
                } elseif (!empty($rawHours[$key]['open']) && !empty($rawHours[$key]['close'])) {
                    $formatted[] = "{$label} ({$rawHours[$key]['open']} - {$rawHours[$key]['close']} WIB)";
                }
            }
            $hours = !empty($formatted) ? implode(', ', $formatted) : 'Senin - Minggu: 08:00 - 20:00 WIB';
        } else {
            $hours = (string) ($store->operational_hours ?: 'Senin - Minggu: 08:00 - 20:00 WIB');
            if (str_contains($norm, 'minggu') && str_contains(strtolower($hours), 'minggu')) {
                return "Jadwal operasional toko {$store->name}: {$hours}.";
            }
        }

        return "Jadwal operasional toko {$store->name}: {$hours}. Anda dapat mengubah jadwal operasional di Pengaturan Toko (/store/settings).";
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

        // 2. Approvals / pending registrations
        if (str_contains($normalizedMessage, 'approval') || str_contains($normalizedMessage, 'persetujuan') || str_contains($normalizedMessage, 'menunggu') || str_contains($normalizedMessage, 'verifikasi kurir') || str_contains($normalizedMessage, 'pendaftaran') || str_contains($normalizedMessage, 'mitra baru') || str_contains($normalizedMessage, 'kurir baru')) {
            return $this->getSuperAdminApprovalsResponse($user, $normalizedMessage);
        }

        // 3. Platform complaints / disputes
        if (str_contains($normalizedMessage, 'komplain') || str_contains($normalizedMessage, 'dispute') || str_contains($normalizedMessage, 'keluhan')) {
            return $this->getSuperAdminComplaintsResponse($user, $normalizedMessage);
        }

        // 4. User counts per role (tanpa PII)
        if (str_contains($normalizedMessage, 'per peran') || str_contains($normalizedMessage, 'jumlah user') || str_contains($normalizedMessage, 'total user') || str_contains($normalizedMessage, 'jumlah pengguna') || str_contains($normalizedMessage, 'total pengguna') || str_contains($normalizedMessage, 'daftar peran') || str_contains($normalizedMessage, 'berapa user') || str_contains($normalizedMessage, 'berapa pengguna')) {
            return $this->getSuperAdminUsersResponse($user, $normalizedMessage);
        }

        // 5. Search for users (sensitive data like email and phone MUST be masked; passwords/tokens never shown)
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

        // 6. Search for stores
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

        // 7. Platform overview & revenue & orders (agregat platform)
        $totalOrders = Order::count();
        $activeStores = Store::where('status', 'approved')->where('is_active', true)->count();
        $pendingStores = Store::where('status', 'pending')->count();
        $totalStores = Store::count();

        $storageTimezone = config('app.timezone', 'UTC');
        $startOfTodayStorage = Carbon::now('Asia/Jakarta')->startOfDay()->setTimezone($storageTimezone);
        $endOfTodayStorage = Carbon::now('Asia/Jakarta')->endOfDay()->setTimezone($storageTimezone);

        $todayRevenue = (float) Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$startOfTodayStorage, $endOfTodayStorage])
            ->sum('amount');

        $totalRevenue = (float) Payment::where('status', 'paid')->sum('amount');
        if ($totalRevenue === 0.0) {
            $totalRevenue = (float) Order::where('payment_status', 'paid')->sum(DB::raw('COALESCE(total_amount, total_price, 0)'));
        }

        $todayRevFmt = 'Rp ' . number_format($todayRevenue, 0, ',', '.');
        $totalRevFmt = 'Rp ' . number_format($totalRevenue, 0, ',', '.');

        $stats = $this->statsService->getPlatformStats();
        return "{$stats} Jumlah toko aktif ({$activeStores} toko mitra aktif di platform, {$pendingStores} menunggu persetujuan dari total {$totalStores} toko terdaftar). Omzet Platform: Hari ini {$todayRevFmt}, Total omzet terbayar {$totalRevFmt}.";
    }

    public function getSuperAdminApprovalsResponse(User $user, string $message = ''): string
    {
        $pendingStores = Store::where('status', 'pending')->count();
        $pendingCouriers = Courier::where('is_verified', false)->count();

        return "Pendaftaran dan verifikasi yang menunggu persetujuan:\n"
            . "- Toko Mitra Baru: {$pendingStores} pendaftaran menunggu persetujuan (status: pending).\n"
            . "- Kurir Baru: {$pendingCouriers} kurir menunggu verifikasi.\n"
            . "Anda dapat memproses verifikasi dan persetujuan melalui menu Verifikasi & Persetujuan di Admin Portal (/admin).";
    }

    public function getSuperAdminUsersResponse(User $user, string $message = ''): string
    {
        $customerCount = User::where(function ($q) {
            $q->where('role', 'customer')
              ->orWhereHas('roles', fn ($r) => $r->where('name', 'customer'));
        })->distinct()->count('id');

        $courierCount = User::where(function ($q) {
            $q->where('role', 'courier')
              ->orWhereHas('roles', fn ($r) => $r->where('name', 'courier'));
        })->distinct()->count('id');

        $mitraCount = User::where(function ($q) {
            $q->whereIn('role', ['store_owner', 'store_admin'])
              ->orWhereHas('roles', fn ($r) => $r->whereIn('name', ['store_owner', 'store_admin']));
        })->distinct()->count('id');

        $adminCount = User::where(function ($q) {
            $q->whereIn('role', ['super_admin', 'platform_owner', 'platform_admin'])
              ->orWhereHas('roles', fn ($r) => $r->whereIn('name', ['super_admin', 'platform_owner', 'platform_admin']));
        })->distinct()->count('id');

        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();

        return "Jumlah pengguna terdaftar di platform LaundryKu (total: {$totalUsers} pengguna, {$activeUsers} aktif):\n"
            . "- Pelanggan (Customer): {$customerCount} pengguna\n"
            . "- Kurir: {$courierCount} pengguna\n"
            . "- Pengelola Toko (Mitra/Admin Toko): {$mitraCount} pengguna\n"
            . "- Administrator Platform: {$adminCount} pengguna\n"
            . "(Catatan: Data sensitif dan PII pelanggan tidak ditampilkan demi kepatuhan privasi).";
    }

    public function getSuperAdminComplaintsResponse(User $user, string $message = ''): string
    {
        $openDisputes = Dispute::whereIn('status', ['open', 'investigating'])->count();
        $resolvedDisputes = Dispute::where('status', 'resolved')->count();
        $rejectedDisputes = Dispute::where('status', 'rejected')->count();
        $totalDisputes = Dispute::count();

        if ($totalDisputes === 0) {
            return "Saat ini belum ada pengajuan komplain atau dispute di platform LaundryKu.";
        }

        return "Statistik komplain & dispute platform LaundryKu:\n"
            . "- Komplain aktif/terbuka: {$openDisputes} kasus (status: open/investigating).\n"
            . "- Komplain selesai/teratasi: {$resolvedDisputes} kasus.\n"
            . "- Komplain ditolak: {$rejectedDisputes} kasus.\n"
            . "- Total komplain: {$totalDisputes} kasus.\n"
            . "Mediasi komplain dapat ditangani melalui menu Manajemen Komplain di Admin Portal (/admin).";
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

