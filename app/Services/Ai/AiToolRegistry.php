<?php

namespace App\Services\Ai;

use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Payment;
use App\Enums\CourierTaskStatus;
use App\Models\SettlementPeriod;
use App\Models\StoreStaff;
use Illuminate\Support\Facades\DB;
use App\Models\Review;
use App\Models\ServicePrice;
use App\Models\Store;
use App\Models\User;
use App\Services\Dashboard\DashboardMetricsService;
use App\Services\Store\StoreDashboardService;
use Carbon\Carbon;

/**
 * The ONLY data path available to the AI assistant.
 *
 * HARD RULES:
 * - Whitelist is fixed: anything not in ALLOWED_TOOLS is rejected outright.
 * - Every function performs an explicit authorization check against the
 *   authenticated actor BEFORE returning any data.
 * - Every function returns a minimal field set: no passwords, no secrets,
 *   no payment credentials (xendit ids, IP, user agent), no audit logs.
 * - All functions are read-only. Nothing here mutates business state.
 * - Zero-Trust & Server-injected context: LLM CANNOT inject user_id, store_id,
 *   or role. Server always derives them from $actor.
 */
class AiToolRegistry
{
    public const ALLOWED_TOOLS = [
        // Shared & Profile
        'get_user_profile',
        'get_store_information',
        'get_services_and_prices',

        // Customer
        'customer_list_orders',
        'customer_get_order_detail',
        'customer_get_order_stats',
        'customer_get_payment_status',
        'customer_get_reviews',
        'customer_get_complaints',

        // Store Admin / Owner
        'store_get_summary',
        'store_list_orders',
        'store_get_order_detail',
        'store_get_services',
        'store_get_couriers',
        'store_get_reviews',
        'store_get_complaints',

        // Courier
        'courier_get_profile',
        'courier_get_tasks',
        'courier_get_task_stats',
        'courier_get_cod_rules',

        // Platform Admin / Super Admin
        'platform_search_users',
        'platform_get_orders',
        'platform_get_courier_activity',
        'platform_get_settlements',
        'platform_explain_admin_menu',
        'platform_get_overview',
        'platform_get_user_stats',
        'platform_get_approvals',
        'platform_get_complaints',

        // Legacy compatibility
        'get_customer_order_status',
        'get_customer_payment_status',
        'get_store_service',
        'get_order_basic_information',
    ];

    protected StoreDashboardService $storeDashboardService;
    protected DashboardMetricsService $dashboardMetricsService;

    public function __construct(
        ?StoreDashboardService $storeDashboardService = null,
        ?DashboardMetricsService $dashboardMetricsService = null
    ) {
        $this->storeDashboardService = $storeDashboardService ?? app(StoreDashboardService::class);
        $this->dashboardMetricsService = $dashboardMetricsService ?? app(DashboardMetricsService::class);
    }

    /**
     * Get tool definitions strictly filtered by the authenticated user's role.
     * Tools outside the user's role are NEVER sent to the LLM.
     */
    public function definitionsForUser(User $user): array
    {
        $roles = $this->getUserRoles($user);
        $isCustomer = in_array('customer', $roles, true) || empty($roles);
        $isStore = in_array('store_owner', $roles, true) || in_array('store_admin', $roles, true);
        $isCourier = in_array('courier', $roles, true);
        $isAdmin = in_array('super_admin', $roles, true) || in_array('platform_owner', $roles, true) || in_array('platform_admin', $roles, true);

        $allowedNames = ['get_user_profile', 'get_store_information', 'get_services_and_prices'];

        if ($isCustomer) {
            $allowedNames = array_merge($allowedNames, [
                'customer_list_orders',
                'customer_get_order_detail',
                'customer_get_order_stats',
                'customer_get_payment_status',
                'customer_get_reviews',
                'customer_get_complaints',
                'get_customer_order_status',
                'get_customer_payment_status',
            ]);
        }

        if ($isStore) {
            $allowedNames = array_merge($allowedNames, [
                'store_get_summary',
                'store_list_orders',
                'store_get_order_detail',
                'store_get_services',
                'store_get_couriers',
                'store_get_reviews',
                'store_get_complaints',
            ]);
        }

        if ($isCourier) {
            $allowedNames = array_merge($allowedNames, [
                'courier_get_profile',
                'courier_get_tasks',
                'courier_get_task_stats',
                'courier_get_cod_rules',
            ]);
        }

        if ($isAdmin) {
            $allowedNames = array_merge($allowedNames, [
                'platform_search_users',
                'platform_get_orders',
                'platform_get_courier_activity',
                'platform_get_settlements',
                'platform_explain_admin_menu',
            ]);
            $allowedNames = array_merge($allowedNames, [
                'platform_get_overview',
                'platform_get_user_stats',
                'platform_get_approvals',
                'platform_get_complaints',
            ]);
        }

        $allDefs = $this->allDefinitions();
        $filtered = [];

        foreach ($allDefs as $def) {
            $toolName = $def['function']['name'] ?? '';
            if (in_array($toolName, $allowedNames, true)) {
                $filtered[] = $def;
            }
        }

        return $filtered;
    }

    /**
     * Legacy definitions compatibility.
     */
    public function definitions(): array
    {
        return $this->allDefinitions();
    }

    /**
     * All OpenAI function-calling definitions.
     * SERVER INJECTION RULE: No tool definition includes user_id, customer_id, or store_id.
     */
    public function allDefinitions(): array
    {
        return [
            // ========== SHARED & PROFILE ==========
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_user_profile',
                    'description' => 'Mendapatkan data profil pengguna yang sedang login (nama, peran, email bermasker, telepon bermasker, dan toko/status kurir terkait).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_store_information',
                    'description' => 'Mendapatkan informasi publik gerai laundry aktif (nama, alamat, kota, rating, jam buka).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'store_id' => ['type' => 'integer', 'description' => 'ID toko (opsional jika nama toko disertakan)'],
                            'store_name' => ['type' => 'string', 'description' => 'Nama toko laundry (opsional)'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_services_and_prices',
                    'description' => 'Mendapatkan daftar layanan aktif dan tarif harga per satuan/kiloan dari sebuah toko laundry.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'store_id' => ['type' => 'integer', 'description' => 'ID toko laundry (opsional)'],
                            'store_name' => ['type' => 'string', 'description' => 'Nama toko laundry (opsional)'],
                        ],
                    ],
                ],
            ],

            // ========== CUSTOMER TOOLS ==========
            [
                'type' => 'function',
                'function' => [
                    'name' => 'customer_list_orders',
                    'description' => 'Melihat daftar pesanan cucian milik pelanggan yang sedang login.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => ['type' => 'string', 'description' => 'Filter status pesanan (misal: "active", "completed", "processing")'],
                            'limit' => ['type' => 'integer', 'description' => 'Jumlah maksimal pesanan yang ditampilkan (default 5, maks 10)'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'customer_get_order_detail',
                    'description' => 'Melihat detail status, berat riil, harga, dan proses cucian pesanan tertentu milik pelanggan login.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'order_number' => ['type' => 'string', 'description' => 'Nomor pesanan (misal: ORD-2026-001)'],
                            'order_id' => ['type' => 'integer', 'description' => 'ID pesanan jika diketahui'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'customer_get_order_stats',
                    'description' => 'Melihat ringkasan statistik pesanan pelanggan (total pesanan, pesanan aktif, pesanan selesai, dan total pengeluaran).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'customer_get_payment_status',
                    'description' => 'Melihat status pembayaran (lunas, pending, metode bayar) untuk pesanan milik pelanggan login.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'order_number' => ['type' => 'string', 'description' => 'Nomor pesanan (misal: ORD-2026-001)'],
                            'order_id' => ['type' => 'integer', 'description' => 'ID pesanan jika diketahui'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'customer_get_reviews',
                    'description' => 'Melihat daftar ulasan atau rating yang pernah diberikan oleh pelanggan login.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => ['type' => 'integer', 'description' => 'Jumlah ulasan maksimal'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'customer_get_complaints',
                    'description' => 'Melihat riwayat komplain atau dispute yang pernah diajukan oleh pelanggan login.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => ['type' => 'integer', 'description' => 'Jumlah komplain maksimal'],
                        ],
                    ],
                ],
            ],

            // ========== STORE ADMIN / OWNER TOOLS ==========
            [
                'type' => 'function',
                'function' => [
                    'name' => 'store_get_summary',
                    'description' => 'Mendapatkan ringkasan dashboard toko mitra yang dikelola (metrik pesanan baru, sedang diproses, siap antar, omzet hari ini, kurir aktif, dan status operasional).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'store_list_orders',
                    'description' => 'Melihat antrean atau riwayat pesanan yang masuk ke toko yang dikelola mitra login.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => ['type' => 'string', 'description' => 'Filter status pesanan di toko (misal: "new", "processing", "ready")'],
                            'limit' => ['type' => 'integer', 'description' => 'Jumlah maksimal pesanan (default 5, maks 10)'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'store_get_order_detail',
                    'description' => 'Melihat rincian pesanan tertentu yang berada di gerai toko yang dikelola mitra.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'order_number' => ['type' => 'string', 'description' => 'Nomor pesanan'],
                            'order_id' => ['type' => 'integer', 'description' => 'ID pesanan'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'store_get_services',
                    'description' => 'Melihat daftar tarif dan layanan yang aktif di toko yang dikelola mitra login.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'store_get_couriers',
                    'description' => 'Melihat daftar kurir terikat toko beserta status ketersediaan (online/siap tugas) dan kendaraan.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'store_get_reviews',
                    'description' => 'Melihat rekap rating rata-rata dan ulasan yang diberikan pelanggan untuk toko mitra.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => ['type' => 'integer', 'description' => 'Jumlah ulasan maksimal'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'store_get_complaints',
                    'description' => 'Melihat daftar komplain atau kendala cucian pelanggan yang masuk ke toko mitra ini.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => ['type' => 'integer', 'description' => 'Jumlah komplain maksimal'],
                        ],
                    ],
                ],
            ],

            // ========== COURIER TOOLS ==========
            [
                'type' => 'function',
                'function' => [
                    'name' => 'courier_get_profile',
                    'description' => 'Mendapatkan profil kurir (nama, toko terafiliasi, jenis kendaraan, plat nomor bermasker, status ketersediaan siap tugas).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'courier_get_tasks',
                    'description' => 'Melihat daftar tugas penjemputan (pickup) atau pengantaran (delivery) yang ditugaskan kepada kurir login.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => ['type' => 'string', 'description' => 'Filter status tugas (misal: "active", "pending", "completed")'],
                            'limit' => ['type' => 'integer', 'description' => 'Jumlah maksimal tugas'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'courier_get_task_stats',
                    'description' => 'Melihat statistik performa tugas kurir (tugas aktif saat ini, tugas selesai hari ini, dan total tugas selesai).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'courier_get_cod_rules',
                    'description' => 'Mendapatkan aturan operasional Cash on Delivery (COD) resmi untuk kurir (batas maksimal nilai order dan prosedur serah terima).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],

            // ========== PLATFORM ADMIN TOOLS ==========
            [
                'type' => 'function',
                'function' => [
                    'name' => 'platform_get_overview',
                    'description' => 'Melihat ringkasan ekosistem platform LaundryKu (total toko, toko aktif, toko pending approval, total pelanggan, kurir, dan volume transaksi).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'platform_get_user_stats',
                    'description' => 'Melihat statistik jumlah pengguna platform berdasarkan peran (customer, mitra toko, kurir).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'platform_get_approvals',
                    'description' => 'Melihat jumlah dan daftar pendaftaran toko mitra yang sedang menunggu verifikasi atau persetujuan admin.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'platform_get_complaints',
                    'description' => 'Melihat daftar komplain atau sengketa transaksi di seluruh platform yang membutuhkan penanganan.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => ['type' => 'integer', 'description' => 'Jumlah komplain maksimal'],
                        ],
                    ],
                ],
            ],

            [
                'type' => 'function',
                'function' => [
                    'name' => 'platform_search_users',
                    'description' => 'Mencari pengguna/akun terdaftar berdasarkan nama, email, atau nomor telepon, beserta status akun dan keterkaitannya dengan toko atau kurir.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Nama, email, atau nomor telepon pengguna'],
                            'limit' => ['type' => 'integer', 'description' => 'Maksimal jumlah pengguna (default 5, max 15)'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'platform_get_orders',
                    'description' => 'Melihat daftar atau agregasi pesanan platform dengan filter status (awaiting_payment, completed, dll.), filter toko, periode, atau pesanan anomali (completed dengan completed_at null).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => ['type' => 'string', 'description' => 'Status pesanan'],
                            'store_id' => ['type' => 'integer', 'description' => 'ID toko mitra (opsional)'],
                            'period' => ['type' => 'string', 'description' => 'Periode waktu (today, this_week, this_month)'],
                            'null_completed_at' => ['type' => 'boolean', 'description' => 'Hanya pesanan completed dengan completed_at null'],
                            'group_by_store' => ['type' => 'boolean', 'description' => 'Kelompokkan jumlah pesanan per toko'],
                            'limit' => ['type' => 'integer', 'description' => 'Jumlah maksimal pesanan'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'platform_get_courier_activity',
                    'description' => 'Melihat aktivitas kurir platform, kurir yang sedang memegang tugas aktif (pickup/delivery), dan ketersediaan kurir.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'only_active' => ['type' => 'boolean', 'description' => 'Hanya kurir dengan tugas aktif berjalan'],
                            'limit' => ['type' => 'integer', 'description' => 'Jumlah maksimal data'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'platform_get_settlements',
                    'description' => 'Melihat ringkasan pengajuan pencairan dana (settlement) toko mitra berdasarkan status (generated/pending, verified, paid).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => ['type' => 'string', 'description' => 'Status settlement (generated/verified/paid)'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'platform_explain_admin_menu',
                    'description' => 'Mendapatkan penjelasan fungsi menu dan modul operasional pada portal Super Admin LaundryKu.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'menu_key' => ['type' => 'string', 'description' => 'Kunci menu (overview, stores, orders, users, couriers, payments, refunds, disputes, audit, commission, ai)'],
                        ],
                    ],
                ],
            ],

            // ========== LEGACY DEFINITIONS ==========
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_customer_order_status',
                    'description' => 'Get the status of a customer order. Only works for orders belonging to the authenticated customer.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'customer_id' => ['type' => 'integer', 'description' => 'The authenticated customer user id'],
                            'order_id' => ['type' => 'integer', 'description' => 'The order id'],
                        ],
                        'required' => ['customer_id', 'order_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_customer_payment_status',
                    'description' => 'Get the payment status for a customer order. Only works for orders belonging to the authenticated customer.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'customer_id' => ['type' => 'integer'],
                            'order_id' => ['type' => 'integer'],
                        ],
                        'required' => ['customer_id', 'order_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_store_service',
                    'description' => 'Get the list of services and prices offered by a store.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'store_id' => ['type' => 'integer'],
                        ],
                        'required' => ['store_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_order_basic_information',
                    'description' => 'Get basic non-sensitive information about an order. Only the order customer or the store owner/staff may access it.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'order_id' => ['type' => 'integer'],
                        ],
                        'required' => ['order_id'],
                    ],
                ],
            ],
        ];
    }

    public function isAllowed(string $name): bool
    {
        return in_array($name, self::ALLOWED_TOOLS, true);
    }

    /**
     * Execute a whitelisted tool with server-enforced authorization and data masking.
     */
    public function execute(string $name, array $arguments, User $actor): array
    {
        if (!$this->isAllowed($name)) {
            return ['error' => 'Function tidak diizinkan. Hanya function resmi yang terdaftar yang boleh dijalankan.'];
        }

        switch ($name) {
            // Profile & Shared
            case 'get_user_profile':
                return $this->getUserProfile($arguments, $actor);
            case 'get_store_information':
                return $this->getStoreInformation($arguments, $actor);
            case 'get_services_and_prices':
                return $this->getServicesAndPrices($arguments, $actor);

            // Customer
            case 'customer_list_orders':
                return $this->customerListOrders($arguments, $actor);
            case 'customer_get_order_detail':
                return $this->customerGetOrderDetail($arguments, $actor);
            case 'customer_get_order_stats':
                return $this->customerGetOrderStats($arguments, $actor);
            case 'customer_get_payment_status':
                return $this->customerGetPaymentStatusTool($arguments, $actor);
            case 'customer_get_reviews':
                return $this->customerGetReviews($arguments, $actor);
            case 'customer_get_complaints':
                return $this->customerGetComplaints($arguments, $actor);

            // Store Admin / Owner
            case 'store_get_summary':
                return $this->storeGetSummary($arguments, $actor);
            case 'store_list_orders':
                return $this->storeListOrders($arguments, $actor);
            case 'store_get_order_detail':
                return $this->storeGetOrderDetail($arguments, $actor);
            case 'store_get_services':
                return $this->storeGetServices($arguments, $actor);
            case 'store_get_couriers':
                return $this->storeGetCouriers($arguments, $actor);
            case 'store_get_reviews':
                return $this->storeGetReviews($arguments, $actor);
            case 'store_get_complaints':
                return $this->storeGetComplaints($arguments, $actor);

            // Courier
            case 'courier_get_profile':
                return $this->courierGetProfile($arguments, $actor);
            case 'courier_get_tasks':
                return $this->courierGetTasks($arguments, $actor);
            case 'courier_get_task_stats':
                return $this->courierGetTaskStats($arguments, $actor);
            case 'courier_get_cod_rules':
                return $this->courierGetCodRules($arguments, $actor);

            // Platform Admin
            case 'platform_search_users':
                return $this->platformSearchUsers($arguments, $actor);
            case 'platform_get_orders':
                return $this->platformGetOrders($arguments, $actor);
            case 'platform_get_courier_activity':
                return $this->platformGetCourierActivity($arguments, $actor);
            case 'platform_get_settlements':
                return $this->platformGetSettlements($arguments, $actor);
            case 'platform_explain_admin_menu':
                return $this->platformExplainAdminMenu($arguments, $actor);
            case 'platform_get_overview':
                return $this->platformGetOverview($arguments, $actor);
            case 'platform_get_user_stats':
                return $this->platformGetUserStats($arguments, $actor);
            case 'platform_get_approvals':
                return $this->platformGetApprovals($arguments, $actor);
            case 'platform_get_complaints':
                return $this->platformGetComplaints($arguments, $actor);

            // Legacy
            case 'get_customer_order_status':
                return $this->getCustomerOrderStatus($arguments, $actor);
            case 'get_customer_payment_status':
                return $this->getCustomerPaymentStatus($arguments, $actor);
            case 'get_store_service':
                return $this->getStoreService($arguments, $actor);
            case 'get_order_basic_information':
                return $this->getOrderBasicInformation($arguments, $actor);

            default:
                return ['error' => 'Function tidak dikenal.'];
        }
    }

    // ==========================================
    // TOOL IMPLEMENTATIONS (AUTHORIZATION + MASKING)
    // ==========================================

    protected function getUserProfile(array $args, User $actor): array
    {
        $roles = $this->getUserRoles($actor);
        $roleLabel = $this->formatRoleLabels($roles);

        $data = [
            'name' => $actor->name,
            'role' => $roleLabel,
            'email' => $this->maskEmail($actor->email),
            'phone' => $this->maskPhone($actor->phone),
        ];

        // Store affiliation if store manager
        if (in_array('store_owner', $roles, true) || in_array('store_admin', $roles, true)) {
            $store = $this->storeDashboardService->resolveStore($actor);
            if ($store) {
                $data['managed_store'] = [
                    'name' => $store->name,
                    'status' => $store->status,
                    'is_active' => $store->is_active ? 'Aktif' : 'Nonaktif',
                    'city' => $store->city,
                ];
            } else {
                $data['managed_store'] = 'Belum terhubung ke gerai';
            }
        }

        // Courier details if courier
        if (in_array('courier', $roles, true)) {
            $courier = Courier::where('user_id', $actor->id)->first();
            if ($courier) {
                $data['courier_status'] = [
                    'is_available' => $courier->is_available ? 'Siap Tugas (Online)' : 'Istirahat (Offline)',
                    'vehicle_type' => $courier->vehicle_type ?? 'Motor',
                    'plate_number' => $this->maskPlate($courier->plate_number),
                    'store_name' => $courier->store?->name ?? 'Toko Laundry',
                ];
            }
        }

        return $data;
    }

    protected function customerListOrders(array $args, User $actor): array
    {
        $limit = min(10, max(1, (int) ($args['limit'] ?? 5)));
        $status = trim((string) ($args['status'] ?? ''));

        $query = Order::where('customer_id', $actor->id)->with('store:id,name')->latest('created_at');

        if ($status === 'active') {
            $query->whereNotIn('status', ['completed', 'cancelled', 'pickup_failed', 'delivery_failed']);
        } elseif ($status === 'completed') {
            $query->where('status', 'completed');
        } elseif ($status !== '') {
            $query->where('status', $status);
        }

        $orders = $query->limit($limit)->get()->map(function ($order) {
            return [
                'order_number' => $order->order_number,
                'store_name' => $order->store?->name ?? 'Toko Laundry',
                'status' => $order->status,
                'status_label' => StoreDashboardService::STATUS_LABELS[$order->status] ?? $order->status,
                'total_amount' => $this->formatRupiah($order->total_amount ?? $order->final_price ?? 0),
                'order_date' => optional($order->order_date)->format('d M Y H:i') ?? optional($order->created_at)->format('d M Y H:i'),
                'is_active' => !in_array($order->status, ['completed', 'cancelled', 'pickup_failed', 'delivery_failed'], true),
            ];
        })->values()->all();

        return [
            'total_found' => count($orders),
            'orders' => $orders,
        ];
    }

    protected function customerGetOrderDetail(array $args, User $actor): array
    {
        $orderNumber = trim((string) ($args['order_number'] ?? ''));
        $orderId = (int) ($args['order_id'] ?? 0);

        $query = Order::where('customer_id', $actor->id)->with('store:id,name');

        if ($orderNumber !== '') {
            $query->where('order_number', $orderNumber);
        } elseif ($orderId > 0) {
            $query->where('id', $orderId);
        } else {
            // Default: pick the most recent order of this customer
            $query->latest('created_at');
        }

        $order = $query->first();

        if (!$order) {
            return ['error' => 'Pesanan tidak ditemukan pada akun Anda. Pastikan nomor pesanan benar.'];
        }

        return [
            'order_number' => $order->order_number,
            'store_name' => $order->store?->name ?? 'Toko Laundry',
            'status' => $order->status,
            'status_label' => StoreDashboardService::STATUS_LABELS[$order->status] ?? $order->status,
            'weight_kg' => (string) ($order->actual_weight ?? $order->weight ?? 'Belum ditimbang'),
            'total_amount' => $this->formatRupiah($order->total_amount ?? $order->final_price ?? 0),
            'payment_status' => $order->payment_status,
            'delivery_type' => $order->delivery_type ?? 'pickup_delivery',
            'order_date' => optional($order->order_date)->format('d M Y H:i') ?? optional($order->created_at)->format('d M Y H:i'),
            'completed_at' => optional($order->completed_at)->format('d M Y H:i'),
        ];
    }

    protected function customerGetOrderStats(array $args, User $actor): array
    {
        $totalOrders = Order::where('customer_id', $actor->id)->count();
        $completedOrders = Order::where('customer_id', $actor->id)->where('status', 'completed')->count();
        $activeOrders = Order::where('customer_id', $actor->id)
            ->whereNotIn('status', ['completed', 'cancelled', 'pickup_failed', 'delivery_failed'])
            ->count();

        $totalSpent = (float) Order::where('customer_id', $actor->id)
            ->where('status', 'completed')
            ->sum('total_amount');

        $latestOrder = Order::where('customer_id', $actor->id)->latest('created_at')->first();

        return [
            'total_orders' => $totalOrders,
            'active_orders' => $activeOrders,
            'completed_orders' => $completedOrders,
            'total_spent' => $this->formatRupiah($totalSpent),
            'latest_order_number' => $latestOrder?->order_number,
            'latest_order_status' => $latestOrder ? (StoreDashboardService::STATUS_LABELS[$latestOrder->status] ?? $latestOrder->status) : null,
        ];
    }

    protected function customerGetPaymentStatusTool(array $args, User $actor): array
    {
        $orderNumber = trim((string) ($args['order_number'] ?? ''));
        $orderId = (int) ($args['order_id'] ?? 0);

        $query = Order::where('customer_id', $actor->id);
        if ($orderNumber !== '') {
            $query->where('order_number', $orderNumber);
        } elseif ($orderId > 0) {
            $query->where('id', $orderId);
        } else {
            $query->latest('created_at');
        }

        $order = $query->first();

        if (!$order) {
            return ['error' => 'Pesanan tidak ditemukan pada akun Anda.'];
        }

        $payment = Payment::where('order_id', $order->id)->latest('id')->first();

        return [
            'order_number' => $order->order_number,
            'order_payment_status' => $order->payment_status,
            'total_bill' => $this->formatRupiah($order->total_amount ?? $order->final_price ?? 0),
            'payment_record' => $payment ? [
                'status' => $payment->status,
                'method' => strtoupper($payment->payment_method ?? 'ONLINE'),
                'amount' => $this->formatRupiah($payment->amount),
                'paid_at' => optional($payment->paid_at)->format('d M Y H:i'),
                'expires_at' => optional($payment->expires_at)->format('d M Y H:i'),
            ] : null,
        ];
    }

    protected function customerGetReviews(array $args, User $actor): array
    {
        $limit = min(10, max(1, (int) ($args['limit'] ?? 5)));
        $reviews = Review::where('customer_id', $actor->id)
            ->with('store:id,name')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'store_name' => $r->store?->name ?? 'Toko Laundry',
                'rating' => (int) $r->rating,
                'comment' => $r->comment,
                'date' => optional($r->created_at)->format('d M Y'),
            ])
            ->values()
            ->all();

        return [
            'total_reviews' => count($reviews),
            'reviews' => $reviews,
        ];
    }

    protected function customerGetComplaints(array $args, User $actor): array
    {
        $limit = min(10, max(1, (int) ($args['limit'] ?? 5)));
        $complaints = Dispute::where(function ($q) use ($actor) {
                $q->where('opened_by', $actor->id)
                  ->orWhereHas('order', fn ($oq) => $oq->where('customer_id', $actor->id));
            })
            ->with('order:id,order_number')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($d) => [
                'order_number' => $d->order?->order_number ?? 'Pesanan',
                'reason' => $d->reason,
                'status' => $d->status,
                'resolution' => $d->resolution_outcome ?? $d->resolution_note,
                'date' => optional($d->created_at)->format('d M Y H:i'),
            ])
            ->values()
            ->all();

        return [
            'total_complaints' => count($complaints),
            'complaints' => $complaints,
        ];
    }

    // ========== STORE ADMIN / OWNER TOOLS ==========

    protected function storeGetSummary(array $args, User $actor): array
    {
        $store = $this->storeDashboardService->resolveStore($actor);
        if (!$store) {
            return ['error' => 'Anda tidak memiliki toko mitra aktif atau terdaftar.'];
        }

        $data = $this->storeDashboardService->getDashboardData($store);

        return [
            'store_name' => $store->name,
            'operational_status' => $store->is_active ? 'Buka (Aktif)' : 'Tutup (Nonaktif)',
            'approval_status' => $store->status,
            'average_rating' => $store->average_rating ? (string) $store->average_rating : null,
            'review_count' => (int) $store->review_count,
            'metrics' => [
                'new_orders' => $data['metrics']['new_orders'],
                'processing_orders' => $data['metrics']['processing_orders'],
                'ready_for_delivery' => $data['metrics']['ready_for_delivery'],
                'completed_today' => $data['metrics']['completed_today'],
                'today_revenue' => $this->formatRupiah($data['metrics']['today_revenue']),
                'active_couriers' => $data['metrics']['active_couriers'],
                'total_couriers' => $data['metrics']['total_couriers'],
            ],
        ];
    }

    protected function storeListOrders(array $args, User $actor): array
    {
        $store = $this->storeDashboardService->resolveStore($actor);
        if (!$store) {
            return ['error' => 'Anda tidak memiliki toko mitra yang terdaftar.'];
        }

        $limit = min(10, max(1, (int) ($args['limit'] ?? 5)));
        $status = trim((string) ($args['status'] ?? ''));

        $query = Order::where('store_id', $store->id)->with('customer:id,name')->latest('created_at');

        if ($status === 'new') {
            $query->whereIn('status', ['created', 'pending_pickup']);
        } elseif ($status === 'processing') {
            $query->whereIn('status', ['at_store', 'received', 'weighed', 'awaiting_price_approval', 'price_approved', 'awaiting_payment', 'paid', 'processing']);
        } elseif ($status === 'ready') {
            $query->whereIn('status', ['ready_for_delivery', 'delivery_assigned', 'out_for_delivery']);
        } elseif ($status !== '') {
            $query->where('status', $status);
        }

        $orders = $query->limit($limit)->get()->map(fn ($o) => [
            'order_number' => $o->order_number,
            'customer_name' => $this->maskCustomerName($o->customer?->name),
            'status' => $o->status,
            'status_label' => StoreDashboardService::STATUS_LABELS[$o->status] ?? $o->status,
            'total_amount' => $this->formatRupiah($o->total_amount ?? $o->final_price ?? 0),
            'created_at' => optional($o->created_at)->format('d M Y H:i'),
        ])->values()->all();

        return [
            'store_name' => $store->name,
            'total_found' => count($orders),
            'orders' => $orders,
        ];
    }

    protected function storeGetOrderDetail(array $args, User $actor): array
    {
        $store = $this->storeDashboardService->resolveStore($actor);
        if (!$store) {
            return ['error' => 'Anda tidak memiliki toko mitra yang terdaftar.'];
        }

        $orderNumber = trim((string) ($args['order_number'] ?? ''));
        $orderId = (int) ($args['order_id'] ?? 0);

        $query = Order::where('store_id', $store->id)->with(['customer:id,name,phone', 'courier.user:id,name']);

        if ($orderNumber !== '') {
            $query->where('order_number', $orderNumber);
        } elseif ($orderId > 0) {
            $query->where('id', $orderId);
        } else {
            $query->latest('created_at');
        }

        $order = $query->first();

        if (!$order) {
            return ['error' => 'Pesanan tidak ditemukan pada toko Anda.'];
        }

        return [
            'store_name' => $store->name,
            'order_number' => $order->order_number,
            'customer_name' => $this->maskCustomerName($order->customer?->name),
            'customer_phone' => $this->maskPhone($order->customer?->phone),
            'status' => $order->status,
            'status_label' => StoreDashboardService::STATUS_LABELS[$order->status] ?? $order->status,
            'weight_kg' => (string) ($order->actual_weight ?? $order->weight ?? 'Belum ditimbang'),
            'total_amount' => $this->formatRupiah($order->total_amount ?? $order->final_price ?? 0),
            'payment_status' => $order->payment_status,
            'courier_assigned' => $order->courier?->user?->name,
            'order_date' => optional($order->order_date)->format('d M Y H:i'),
        ];
    }

    protected function storeGetServices(array $args, User $actor): array
    {
        $store = $this->storeDashboardService->resolveStore($actor);
        if (!$store) {
            return ['error' => 'Anda tidak memiliki toko mitra yang terdaftar.'];
        }

        $services = ServicePrice::where('store_id', $store->id)
            ->with('service:id,name')
            ->get()
            ->map(fn ($sp) => [
                'service_name' => $sp->service?->name ?? 'Layanan',
                'price_per_unit' => $this->formatRupiah($sp->price_per_unit),
                'unit' => $sp->unit,
                'is_active' => (bool) $sp->is_active,
            ])
            ->values()
            ->all();

        return [
            'store_name' => $store->name,
            'services' => $services,
        ];
    }

    protected function storeGetCouriers(array $args, User $actor): array
    {
        $store = $this->storeDashboardService->resolveStore($actor);
        if (!$store) {
            return ['error' => 'Anda tidak memiliki toko mitra yang terdaftar.'];
        }

        $couriers = Courier::where('store_id', $store->id)
            ->with('user:id,name,phone')
            ->get()
            ->map(fn ($c) => [
                'name' => $c->user?->name ?? 'Kurir',
                'is_available' => (bool) $c->is_available ? 'Siap Tugas (Online)' : 'Tidak Siap (Offline)',
                'vehicle_type' => $c->vehicle_type ?? 'Motor',
                'phone' => $this->maskPhone($c->user?->phone),
            ])
            ->values()
            ->all();

        return [
            'store_name' => $store->name,
            'total_couriers' => count($couriers),
            'couriers' => $couriers,
        ];
    }

    protected function storeGetReviews(array $args, User $actor): array
    {
        $store = $this->storeDashboardService->resolveStore($actor);
        if (!$store) {
            return ['error' => 'Anda tidak memiliki toko mitra yang terdaftar.'];
        }

        $limit = min(10, max(1, (int) ($args['limit'] ?? 5)));
        $reviews = Review::where('store_id', $store->id)
            ->with('customer:id,name')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'customer_name' => $this->maskCustomerName($r->customer?->name),
                'rating' => (int) $r->rating,
                'comment' => $r->comment,
                'date' => optional($r->created_at)->format('d M Y'),
            ])
            ->values()
            ->all();

        return [
            'store_name' => $store->name,
            'average_rating' => $store->average_rating ? (string) $store->average_rating : 'Belum ada rating',
            'review_count' => (int) $store->review_count,
            'recent_reviews' => $reviews,
        ];
    }

    protected function storeGetComplaints(array $args, User $actor): array
    {
        $store = $this->storeDashboardService->resolveStore($actor);
        if (!$store) {
            return ['error' => 'Anda tidak memiliki toko mitra yang terdaftar.'];
        }

        $limit = min(10, max(1, (int) ($args['limit'] ?? 5)));
        $complaints = Dispute::whereHas('order', fn ($q) => $q->where('store_id', $store->id))
            ->with('order:id,order_number')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($d) => [
                'order_number' => $d->order?->order_number ?? 'Pesanan',
                'reason' => $d->reason,
                'status' => $d->status,
                'resolution' => $d->resolution_outcome ?? $d->resolution_note,
                'created_at' => optional($d->created_at)->format('d M Y H:i'),
            ])
            ->values()
            ->all();

        return [
            'store_name' => $store->name,
            'total_complaints' => count($complaints),
            'complaints' => $complaints,
        ];
    }

    // ========== COURIER TOOLS ==========

    protected function courierGetProfile(array $args, User $actor): array
    {
        $courier = Courier::where('user_id', $actor->id)->with('store:id,name')->first();
        if (!$courier) {
            return ['error' => 'Akun Anda tidak terdaftar sebagai kurir di LaundryKu.'];
        }

        return [
            'name' => $actor->name,
            'phone' => $this->maskPhone($actor->phone),
            'store_name' => $courier->store?->name ?? 'Toko Laundry',
            'vehicle_type' => $courier->vehicle_type ?? 'Motor',
            'plate_number' => $this->maskPlate($courier->plate_number),
            'is_available' => (bool) $courier->is_available ? 'Siap Tugas (Online)' : 'Istirahat (Offline)',
        ];
    }

    protected function courierGetTasks(array $args, User $actor): array
    {
        $courier = Courier::where('user_id', $actor->id)->first();
        if (!$courier) {
            return ['error' => 'Akun Anda tidak terdaftar sebagai kurir.'];
        }

        $limit = min(10, max(1, (int) ($args['limit'] ?? 5)));
        $status = trim((string) ($args['status'] ?? ''));

        $query = CourierTask::where('courier_id', $courier->id)
            ->with('order:id,order_number')
            ->latest('created_at');

        if ($status === 'active') {
            $query->whereIn('status', ['assigned', 'accepted', 'in_progress']);
        } elseif ($status === 'completed') {
            $query->where('status', 'completed');
        } elseif ($status !== '') {
            $query->where('status', $status);
        }

        $tasks = $query->limit($limit)->get()->map(fn ($t) => [
            'id' => $t->id,
            'order_number' => $t->order?->order_number ?? 'Pesanan',
            'task_type' => $t->task_type === 'pickup' ? 'Penjemputan (Pickup)' : 'Pengantaran (Delivery)',
            'status' => $t->status,
            'notes' => $t->notes,
            'date' => optional($t->created_at)->format('d M Y H:i'),
        ])->values()->all();

        return [
            'total_tasks' => count($tasks),
            'tasks' => $tasks,
        ];
    }

    protected function courierGetTaskStats(array $args, User $actor): array
    {
        $courier = Courier::where('user_id', $actor->id)->first();
        if (!$courier) {
            return ['error' => 'Akun Anda tidak terdaftar sebagai kurir.'];
        }

        $activeTasks = CourierTask::where('courier_id', $courier->id)
            ->whereIn('status', ['assigned', 'accepted', 'arrived', 'picked_up'])
            ->count();

        $completedToday = CourierTask::where('courier_id', $courier->id)
            ->whereIn('status', ['delivered', 'completed'])
            ->whereDate('completed_at', Carbon::today())
            ->count();

        $lifetimeCompleted = CourierTask::where('courier_id', $courier->id)
            ->whereIn('status', ['delivered', 'completed'])
            ->count();

        return [
            'active_tasks' => $activeTasks,
            'completed_tasks_today' => $completedToday,
            'total_completed_lifetime' => $lifetimeCompleted,
            'is_available' => (bool) $courier->is_available ? 'Siap Tugas' : 'Offline',
        ];
    }

    protected function courierGetCodRules(array $args, User $actor): array
    {
        return [
            'max_cod_amount' => 'Rp 500.000',
            'rules' => [
                'Batas nilai pesanan tunai (COD) maksimal adalah Rp 500.000 per transaksi.',
                'Kurir wajib menghitung dan memastikan uang tunai diterima penuh dari pelanggan sebelum menyelesaikan status order.',
                'Setoran uang tunai COD wajib diserahkan langsung ke kasir toko mitra pada hari kerja yang sama.',
                'Dilarang menyerahkan cucian jika pelanggan belum melunasi tagihan COD.',
            ],
        ];
    }

    // ========== PLATFORM ADMIN TOOLS ==========

    protected function platformGetOverview(array $args, User $actor): array
    {
        $roles = $this->getUserRoles($actor);
        $isAdmin = in_array('super_admin', $roles, true) || in_array('platform_owner', $roles, true) || in_array('platform_admin', $roles, true);

        if (!$isAdmin) {
            return ['error' => 'Akses ditolak: Hanya super administrator platform yang dapat mengakses data ini.'];
        }

        return [
            'gmv_today' => $this->formatRupiah((float) $this->dashboardMetricsService->get('platform', 'gmv_today')),
            'orders_today' => (int) $this->dashboardMetricsService->get('platform', 'orders_today'),
            'orders_in_progress' => (int) $this->dashboardMetricsService->get('platform', 'orders_in_progress'),
            'stores_total' => (int) $this->dashboardMetricsService->get('platform', 'stores_total'),
            'stores_active' => (int) $this->dashboardMetricsService->get('platform', 'stores_active'),
            'stores_pending' => (int) $this->dashboardMetricsService->get('platform', 'stores_pending'),
            'customers_total' => (int) $this->dashboardMetricsService->get('platform', 'customers_total'),
            'couriers_total' => (int) $this->dashboardMetricsService->get('platform', 'couriers_total'),
        ];
    }

    protected function platformGetUserStats(array $args, User $actor): array
    {
        $roles = $this->getUserRoles($actor);
        $isAdmin = in_array('super_admin', $roles, true) || in_array('platform_owner', $roles, true) || in_array('platform_admin', $roles, true);

        if (!$isAdmin) {
            return ['error' => 'Akses ditolak: Hanya admin platform yang dapat melihat statistik pengguna.'];
        }

        $customers = User::whereHas('roles', fn ($q) => $q->where('name', 'customer'))->count();
        $storeManagers = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['store_owner', 'store_admin']))->count();
        $couriers = User::whereHas('roles', fn ($q) => $q->where('name', 'courier'))->count();
        $totalUsers = User::count();

        return [
            'total_users' => $totalUsers,
            'customers' => $customers,
            'store_managers' => $storeManagers,
            'couriers' => $couriers,
        ];
    }

    protected function platformGetApprovals(array $args, User $actor): array
    {
        $roles = $this->getUserRoles($actor);
        $isAdmin = in_array('super_admin', $roles, true) || in_array('platform_owner', $roles, true) || in_array('platform_admin', $roles, true);

        if (!$isAdmin) {
            return ['error' => 'Akses ditolak: Hanya admin platform yang dapat melihat data persetujuan.'];
        }

        $pendingStores = Store::whereIn('status', ['submitted', 'resubmitted', 'under_review'])
            ->select('id', 'name', 'city', 'status', 'created_at')
            ->limit(10)
            ->get()
            ->map(fn ($s) => [
                'name' => $s->name,
                'city' => $s->city,
                'status' => $s->status,
                'submitted_at' => optional($s->created_at)->format('d M Y'),
            ])
            ->values()
            ->all();

        return [
            'pending_count' => count($pendingStores),
            'stores_awaiting_approval' => $pendingStores,
        ];
    }

    protected function platformGetComplaints(array $args, User $actor): array
    {
        $roles = $this->getUserRoles($actor);
        $isAdmin = in_array('super_admin', $roles, true) || in_array('platform_owner', $roles, true) || in_array('platform_admin', $roles, true);

        if (!$isAdmin) {
            return ['error' => 'Akses ditolak: Hanya admin platform yang dapat melihat komplain platform.'];
        }

        $limit = min(10, max(1, (int) ($args['limit'] ?? 5)));
        $complaints = Dispute::with(['order.store:id,name', 'order.customer:id,name', 'openedBy:id,name'])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($d) => [
                'order_number' => $d->order?->order_number ?? 'Pesanan',
                'store_name' => $d->order?->store?->name ?? 'Toko Laundry',
                'customer_name' => $this->maskCustomerName($d->order?->customer?->name ?? $d->openedBy?->name),
                'reason' => $d->reason,
                'status' => $d->status,
                'created_at' => optional($d->created_at)->format('d M Y H:i'),
            ])
            ->values()
            ->all();

        return [
            'total_complaints' => count($complaints),
            'complaints' => $complaints,
        ];
    }

    protected function platformSearchUsers(array $args, User $actor): array
    {
        $roles = $this->getUserRoles($actor);
        $isAdmin = in_array('super_admin', $roles, true) || in_array('platform_owner', $roles, true) || in_array('platform_admin', $roles, true);

        if (!$isAdmin) {
            return ['error' => 'Akses ditolak: Hanya super administrator platform yang dapat mengakses data ini.'];
        }

        $term = trim((string) ($args['query'] ?? ''));
        if ($term === '') {
            return ['error' => 'Parameter pencarian pengguna (query) harus diisi.'];
        }

        // Clean query if wrapped in conversation phrasing
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $term, $em)) {
            $searchTerm = $em[0];
        } else {
            $cleaned = preg_replace('/^(cari\s+(pengguna|user|akun)\s+(bernama\s+|nama\s+)?|bernama\s+|nama\s+|akun\s+|pengguna\s+|user\s+)/iu', '', $term);
            $cleaned = preg_replace('/(\s+terhubung\s+ke\s+toko\s+mana|\s+terhubung\s+kemana|\?)+$/iu', '', $cleaned);
            $searchTerm = trim($cleaned) !== '' ? trim($cleaned) : $term;
        }

        $limit = min(15, max(1, (int) ($args['limit'] ?? 5)));
        $users = User::where('name', 'like', "%{$searchTerm}%")
            ->orWhere('email', 'like', "%{$searchTerm}%")
            ->orWhere('phone', 'like', "%{$searchTerm}%")
            ->limit($limit)
            ->get();

        if ($users->isEmpty()) {
            return [
                'total' => 0,
                'users' => [],
                'message' => "Pengguna dengan kata kunci '{$term}' tidak ditemukan di sistem LaundryKu.",
            ];
        }

        $results = [];
        foreach ($users as $u) {
            $linkage = 'Tidak ada keterkaitan khusus';
            $ownedStores = Store::where('owner_user_id', $u->id)->pluck('name')->all();
            if (!empty($ownedStores)) {
                $linkage = 'Pemilik Toko: ' . implode(', ', $ownedStores);
            } else {
                $staffStores = StoreStaff::where('user_id', $u->id)->with('store:id,name')->get()->pluck('store.name')->filter()->all();
                if (!empty($staffStores)) {
                    $linkage = 'Staf Toko: ' . implode(', ', $staffStores);
                } else {
                    $courier = Courier::where('user_id', $u->id)->with('store:id,name')->first();
                    if ($courier) {
                        $linkage = $courier->store ? "Kurir pada Toko: {$courier->store->name}" : 'Kurir Platform Independen';
                    } elseif ($u->isCustomer()) {
                        $linkage = 'Pelanggan Umum LaundryKu';
                    }
                }
            }

            $results[] = [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->role ?? 'customer',
                'status' => $u->is_active ? 'aktif' : 'nonaktif',
                'masked_email' => $this->maskEmail($u->email),
                'masked_phone' => $this->maskPhone($u->phone),
                'linkage' => $linkage,
                'created_at' => optional($u->created_at)->format('d M Y'),
            ];
        }

        return [
            'total' => count($results),
            'users' => $results,
        ];
    }

    protected function platformGetOrders(array $args, User $actor): array
    {
        $roles = $this->getUserRoles($actor);
        $isAdmin = in_array('super_admin', $roles, true) || in_array('platform_owner', $roles, true) || in_array('platform_admin', $roles, true);

        if (!$isAdmin) {
            return ['error' => 'Akses ditolak: Hanya super administrator platform yang dapat mengakses data ini.'];
        }

        $status = trim((string) ($args['status'] ?? ''));
        $storeId = (int) ($args['store_id'] ?? 0);
        $period = trim((string) ($args['period'] ?? ''));
        $nullCompletedAt = (bool) ($args['null_completed_at'] ?? false);
        $groupByStore = (bool) ($args['group_by_store'] ?? false);
        $limit = min(20, max(1, (int) ($args['limit'] ?? 10)));

        $query = Order::with(['store:id,name', 'customer:id,name']);

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($storeId > 0) {
            $query->where('store_id', $storeId);
        }

        if ($nullCompletedAt) {
            $query->whereNull('completed_at');
        }

        if ($period === 'today') {
            $query->where('order_date', '>=', now()->startOfDay());
        } elseif ($period === 'this_week') {
            $query->where('order_date', '>=', now()->startOfWeek());
        } elseif ($period === 'this_month') {
            $query->where('order_date', '>=', now()->startOfMonth());
        }

        if ($groupByStore) {
            $counts = (clone $query)->select('store_id', DB::raw('count(*) as total'))
                ->groupBy('store_id')
                ->with('store:id,name')
                ->get()
                ->map(fn ($r) => [
                    'store_name' => $r->store?->name ?? 'Toko #' . $r->store_id,
                    'count' => (int) $r->total,
                ])
                ->all();

            return [
                'total_groups' => count($counts),
                'breakdown' => $counts,
            ];
        }

        $orders = $query->latest('id')->limit($limit)->get()->map(fn ($o) => [
            'order_number' => $o->order_number,
            'store_name' => $o->store?->name ?? 'Toko',
            'customer_name' => $this->maskCustomerName($o->customer?->name),
            'status' => $o->status,
            'payment_status' => $o->payment_status,
            'total_amount' => (float) ($o->total_amount ?: $o->total_price ?: 0),
            'order_date' => optional($o->order_date)->format('d M Y H:i'),
            'completed_at' => optional($o->completed_at)->format('d M Y H:i'),
        ])->all();

        return [
            'total' => count($orders),
            'orders' => $orders,
        ];
    }

    protected function platformGetCourierActivity(array $args, User $actor): array
    {
        $roles = $this->getUserRoles($actor);
        $isAdmin = in_array('super_admin', $roles, true) || in_array('platform_owner', $roles, true) || in_array('platform_admin', $roles, true);

        if (!$isAdmin) {
            return ['error' => 'Akses ditolak: Hanya super administrator platform yang dapat mengakses data ini.'];
        }

        $onlyActive = (bool) ($args['only_active'] ?? true);
        $limit = min(20, max(1, (int) ($args['limit'] ?? 10)));

        $activeTaskStatuses = [CourierTaskStatus::ASSIGNED->value, CourierTaskStatus::ACCEPTED->value, CourierTaskStatus::ARRIVED->value, CourierTaskStatus::PICKED_UP->value];

        if ($onlyActive) {
            $activeTasks = CourierTask::with(['courier.user:id,name', 'courier.store:id,name', 'order:id,order_number,status'])
                ->whereIn('status', $activeTaskStatuses)
                ->latest('assigned_at')
                ->limit($limit)
                ->get()
                ->map(fn ($t) => [
                    'courier_name' => $t->courier?->user?->name ?? 'Kurir',
                    'store_name' => $t->courier?->store?->name ?? 'Independen',
                    'task_type' => $t->task_type,
                    'status' => $t->status,
                    'order_number' => $t->order?->order_number ?? '-',
                    'assigned_at' => optional($t->assigned_at)->format('d M Y H:i'),
                ])
                ->all();

            return [
                'total_active_tasks' => count($activeTasks),
                'active_tasks' => $activeTasks,
            ];
        }

        $totalCouriers = Courier::count();
        $availableCouriers = Courier::where('is_available', true)->count();
        $busyCouriers = Courier::whereHas('tasks', fn ($q) => $q->whereIn('status', $activeTaskStatuses))->count();

        return [
            'total_couriers' => $totalCouriers,
            'available_couriers' => $availableCouriers,
            'busy_couriers' => $busyCouriers,
        ];
    }

    protected function platformGetSettlements(array $args, User $actor): array
    {
        $roles = $this->getUserRoles($actor);
        $isAdmin = in_array('super_admin', $roles, true) || in_array('platform_owner', $roles, true) || in_array('platform_admin', $roles, true);

        if (!$isAdmin) {
            return ['error' => 'Akses ditolak: Hanya super administrator platform yang dapat mengakses data ini.'];
        }

        $status = trim((string) ($args['status'] ?? 'generated'));
        $periods = SettlementPeriod::with('store:id,name')
            ->where('status', $status)
            ->latest()
            ->limit(15)
            ->get();

        $totalAmount = (float) $periods->sum('net_amount');
        $items = $periods->map(fn ($p) => [
            'reference' => $p->reference,
            'store_name' => $p->store?->name ?? 'Toko',
            'net_amount' => (float) $p->net_amount,
            'order_count' => (int) $p->order_count,
            'period' => optional($p->period_start)->format('d/m/Y') . ' - ' . optional($p->period_end)->format('d/m/Y'),
            'status' => $p->status,
        ])->all();

        return [
            'status' => $status,
            'total_settlements' => count($items),
            'total_nominal' => $totalAmount,
            'formatted_nominal' => 'Rp ' . number_format($totalAmount, 0, ',', '.'),
            'items' => $items,
        ];
    }

    protected function platformExplainAdminMenu(array $args, User $actor): array
    {
        $roles = $this->getUserRoles($actor);
        $isAdmin = in_array('super_admin', $roles, true) || in_array('platform_owner', $roles, true) || in_array('platform_admin', $roles, true);

        if (!$isAdmin) {
            return ['error' => 'Akses ditolak: Hanya super administrator platform yang dapat mengakses data ini.'];
        }

        $menus = [
            'overview' => 'Menu Overview menyajikan ringkasan ekosistem LaundryKu: Gross Merchandise Value (GMV), jumlah transaksi hari ini, toko aktif, pelanggan aktif, kurir siap tugas, dan monitoring keamanan/audit log.',
            'stores' => 'Menu Toko & Approval digunakan untuk memverifikasi pendaftaran toko baru, menyetujui (approve), menolak (reject), menonaktifkan (suspend), atau mengaktifkan kembali mitra toko.',
            'orders' => 'Menu Pesanan memantau seluruh transaksi di platform secara lintas toko mitra, status pencucian, riwayat status, kurir yang bertugas, dan detail pembayaran.',
            'users' => 'Menu Manajemen Pengguna / Pelanggan digunakan untuk melihat daftar pengguna, memantau status aktif/nonaktif akun, memeriksa role (customer, kurir, mitra), dan riwayat pendaftaran.',
            'couriers' => 'Menu Kurir memantau daftar mitra kurir, verifikasi dokumen SIM/KTP/kendaraan, memantau ketersediaan kurir, serta keterkaitan kurir dengan toko tertentu.',
            'payments' => 'Menu Pembayaran menampilkan riwayat transaksi pembayaran online (QRIS/VA via Xendit) dan transaksi COD (Cash on Delivery) secara real-time.',
            'refunds' => 'Menu Refund digunakan untuk memproses pengembalian dana kepada pelanggan untuk pesanan yang dibatalkan sesuai kebijakan garansi dan persetujuan admin.',
            'disputes' => 'Menu Penyelesaian Sengketa (Disputes) adalah wadah mediasi keluhan cucian rusak/hilang antara pelanggan dan toko mitra dalam batas waktu garansi 1x24 jam.',
            'audit' => 'Menu Audit Log mencatat seluruh rekam jejak aktivitas keamanan, login gagal, dan perubahan data penting di platform untuk akuntabilitas operasional.',
            'commission' => 'Menu Pengaturan Komisi mengatur persentase pembagian komisi platform per layanan laundry.',
            'ai' => 'Menu Monitoring AI menyajikan transparansi biaya, konsumsi token, dan riwayat panggilan OpenAI Assistant untuk evaluasi operasional asisten.',
        ];

        $key = trim((string) ($args['menu_key'] ?? ''));
        if ($key !== '' && isset($menus[$key])) {
            return [
                'menu' => $key,
                'explanation' => $menus[$key],
            ];
        }

        return [
            'overview' => 'Daftar menu utama di Portal Super Admin LaundryKu:',
            'menus' => $menus,
        ];
    }

    // ========== PUBLIC TOOLS ==========

    protected function getStoreInformation(array $args, User $actor): array
    {
        $storeId = (int) ($args['store_id'] ?? 0);
        $storeName = trim((string) ($args['store_name'] ?? ''));

        $query = Store::query();
        if ($storeId > 0) {
            $query->where('id', $storeId);
        } elseif ($storeName !== '') {
            $query->where('name', 'like', "%{$storeName}%");
        } else {
            $query->where('status', 'approved')->where('is_active', true);
        }

        $store = $query->first();

        if (!$store) {
            return ['error' => 'Informasi tidak tersedia: toko tidak ditemukan.'];
        }

        if (!$this->storeVisibleTo($store, $actor)) {
            return ['error' => 'Informasi tidak tersedia: toko tidak dapat diakses.'];
        }

        return [
            'name' => $store->name,
            'city' => $store->city,
            'address' => $store->address,
            'status' => $store->status,
            'is_active' => $store->is_active ? 'Buka' : 'Tutup',
            'average_rating' => $store->average_rating !== null ? (string) $store->average_rating : null,
            'review_count' => (int) $store->review_count,
        ];
    }

    protected function getServicesAndPrices(array $args, User $actor): array
    {
        $storeId = (int) ($args['store_id'] ?? 0);
        $storeName = trim((string) ($args['store_name'] ?? ''));

        $query = Store::query();
        if ($storeId > 0) {
            $query->where('id', $storeId);
        } elseif ($storeName !== '') {
            $query->where('name', 'like', "%{$storeName}%");
        } else {
            $query->where('status', 'approved')->where('is_active', true);
        }

        $store = $query->first();

        if (!$store) {
            return ['error' => 'Informasi tidak tersedia: toko tidak ditemukan.'];
        }

        if (!$this->storeVisibleTo($store, $actor)) {
            return ['error' => 'Informasi tidak tersedia: toko tidak dapat diakses.'];
        }

        $services = ServicePrice::where('store_id', $store->id)
            ->where('is_active', true)
            ->with('service:id,name')
            ->get()
            ->map(fn ($sp) => [
                'service_name' => optional($sp->service)->name,
                'price_per_unit' => $this->formatRupiah($sp->price_per_unit),
                'unit' => $sp->unit,
            ])
            ->values()
            ->all();

        return [
            'store_name' => $store->name,
            'services' => $services,
        ];
    }

    // ========== LEGACY AUTHORIZED FUNCTIONS ==========

    protected function getCustomerOrderStatus(array $args, User $actor): array
    {
        if ((int) $actor->id !== (int) ($args['customer_id'] ?? 0)) {
            return ['error' => 'Akses ditolak: Anda tidak diizinkan melihat data customer lain.'];
        }

        $order = Order::where('id', (int) ($args['order_id'] ?? 0))
            ->where('customer_id', $actor->id)
            ->first();

        if (!$order) {
            return ['error' => 'Informasi tidak tersedia: order tidak ditemukan.'];
        }

        return [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'order_date' => optional($order->order_date)->toDateTimeString(),
            'completed_at' => optional($order->completed_at)->toDateTimeString(),
        ];
    }

    protected function getCustomerPaymentStatus(array $args, User $actor): array
    {
        if ((int) $actor->id !== (int) ($args['customer_id'] ?? 0)) {
            return ['error' => 'Akses ditolak: Anda tidak diizinkan melihat data customer lain.'];
        }

        $order = Order::where('id', (int) ($args['order_id'] ?? 0))
            ->where('customer_id', $actor->id)
            ->first();

        if (!$order) {
            return ['error' => 'Informasi tidak tersedia: order tidak ditemukan.'];
        }

        $payment = Payment::where('order_id', $order->id)->latest('id')->first();

        if (!$payment) {
            return [
                'order_number' => $order->order_number,
                'payment_status' => $order->payment_status,
                'detail' => 'Informasi tidak tersedia: belum ada record pembayaran.',
            ];
        }

        return [
            'order_number' => $order->order_number,
            'payment_status' => $payment->status,
            'payment_method' => $payment->payment_method,
            'amount' => (string) $payment->amount,
            'expires_at' => optional($payment->expires_at)->toDateTimeString(),
            'paid_at' => optional($payment->paid_at)->toDateTimeString(),
        ];
    }

    protected function getStoreService(array $args, User $actor): array
    {
        return $this->getServicesAndPrices($args, $actor);
    }

    protected function getOrderBasicInformation(array $args, User $actor): array
    {
        $order = Order::find((int) ($args['order_id'] ?? 0));

        if (!$order) {
            return ['error' => 'Informasi tidak tersedia: order tidak ditemukan.'];
        }

        $isCustomer = (int) $order->customer_id === (int) $actor->id;
        $isStoreSide = $this->userHasStoreAccess($actor, $order->store_id);

        if (!$isCustomer && !$isStoreSide) {
            return ['error' => 'Akses ditolak: Anda tidak diizinkan melihat data order ini.'];
        }

        return [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'order_date' => optional($order->order_date)->toDateTimeString(),
            'weight' => $order->actual_weight !== null ? (string) $order->actual_weight : (string) $order->weight,
        ];
    }

    // ========== HELPER FUNCTIONS ==========

    public function getUserRoles(User $user): array
    {
        $roles = [];
        if ($user->relationLoaded('roles') || method_exists($user, 'roles')) {
            try {
                $roles = $user->roles()->pluck('name')->toArray();
            } catch (\Throwable $e) {
                // fallback
            }
        }
        if (empty($roles) && !empty($user->role)) {
            $roles[] = $user->role;
        }
        return array_unique(array_filter($roles));
    }

    public function formatRoleLabels(array $roles): string
    {
        $labels = [
            'customer' => 'Pelanggan',
            'store_owner' => 'Pemilik Toko (Mitra)',
            'store_admin' => 'Admin Toko',
            'courier' => 'Kurir',
            'super_admin' => 'Super Administrator',
            'platform_owner' => 'Pemilik Platform',
            'platform_admin' => 'Admin Platform',
        ];

        $named = array_map(fn ($r) => $labels[$r] ?? ucfirst($r), $roles);
        return !empty($named) ? implode(', ', $named) : 'Pengguna Terdaftar';
    }

    public function maskPhone(?string $phone): ?string
    {
        if (!$phone || strlen($phone) < 6) {
            return $phone ? '***' : null;
        }
        $len = strlen($phone);
        $prefix = substr($phone, 0, 4);
        $suffix = substr($phone, -3);
        return $prefix . str_repeat('*', max(3, $len - 7)) . $suffix;
    }

    public function maskEmail(?string $email): ?string
    {
        if (!$email || !str_contains($email, '@')) {
            return $email;
        }
        [$local, $domain] = explode('@', $email, 2);
        if (strlen($local) <= 2) {
            $maskedLocal = substr($local, 0, 1) . '***';
        } else {
            $maskedLocal = substr($local, 0, 1) . '***' . substr($local, -1);
        }
        return $maskedLocal . '@' . $domain;
    }

    public function maskPlate(?string $plate): ?string
    {
        if (!$plate) {
            return null;
        }
        $parts = preg_split('/\s+/', trim($plate));
        if (count($parts) >= 3) {
            return $parts[0] . ' **** ' . $parts[count($parts) - 1];
        }
        return substr($plate, 0, 2) . ' **** ' . substr($plate, -2);
    }

    public function maskCustomerName(?string $name): string
    {
        $name = trim((string) ($name ?? 'Pelanggan'));
        $parts = explode(' ', $name);
        if (count($parts) === 1) {
            return $parts[0];
        }
        return $parts[0] . ' ' . substr($parts[1], 0, 1) . '***';
    }

    public function formatRupiah(float|int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    protected function storeVisibleTo(Store $store, User $actor): bool
    {
        if ($store->status === 'approved' && $store->is_active) {
            return true;
        }

        return $this->userHasStoreAccess($actor, $store->id);
    }

    protected function userHasStoreAccess(User $user, int $storeId): bool
    {
        $isOwner = Store::where('id', $storeId)->where('owner_user_id', $user->id)->exists();
        $isStaff = $user->storeStaff()->where('store_id', $storeId)->where('is_active', true)->exists();

        return $isOwner || $isStaff;
    }
}