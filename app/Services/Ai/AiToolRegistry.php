<?php

namespace App\Services\Ai;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ServicePrice;
use App\Models\Store;
use App\Models\User;

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
 */
class AiToolRegistry
{
    public const ALLOWED_TOOLS = [
        'get_customer_order_status',
        'get_customer_payment_status',
        'get_store_information',
        'get_store_service',
        'get_order_basic_information',
    ];

    /**
     * OpenAI function-calling definitions (whitelist only).
     */
    public function definitions(): array
    {
        return [
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
                    'name' => 'get_store_information',
                    'description' => 'Get public information about an approved, active store.',
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
     * Execute a whitelisted tool with authorization.
     * Returns a plain array (serialized back to the model as the tool result).
     * Never throws on authorization failure — returns an error payload instead,
     * so the model can tell the user the information is unavailable.
     */
    public function execute(string $name, array $arguments, User $actor): array
    {
        if (!$this->isAllowed($name)) {
            return ['error' => 'Function tidak diizinkan. Hanya function resmi yang terdaftar yang boleh dijalankan.'];
        }

        switch ($name) {
            case 'get_customer_order_status':
                return $this->getCustomerOrderStatus($arguments, $actor);
            case 'get_customer_payment_status':
                return $this->getCustomerPaymentStatus($arguments, $actor);
            case 'get_store_information':
                return $this->getStoreInformation($arguments, $actor);
            case 'get_store_service':
                return $this->getStoreService($arguments, $actor);
            case 'get_order_basic_information':
                return $this->getOrderBasicInformation($arguments, $actor);
            default:
                return ['error' => 'Function tidak dikenal.'];
        }
    }

    // ========== AUTHORIZED FUNCTIONS ==========

    protected function getCustomerOrderStatus(array $args, User $actor): array
    {
        // Authorization: the actor must BE the customer being asked about.
        if ((int) $actor->id !== (int) ($args['customer_id'] ?? 0)) {
            return ['error' => 'Akses ditolak: Anda tidak diizinkan melihat data customer lain.'];
        }

        $order = Order::where('id', (int) ($args['order_id'] ?? 0))
            ->where('customer_id', $actor->id)
            ->first();

        if (!$order) {
            return ['error' => 'Informasi tidak tersedia: order tidak ditemukan.'];
        }

        // Minimal field set — status only, no money, no addresses.
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

        // Minimal + safe: NO xendit ids, NO customer_ip, NO user agent, NO webhook data.
        return [
            'order_number' => $order->order_number,
            'payment_status' => $payment->status,
            'payment_method' => $payment->payment_method,
            'amount' => (string) $payment->amount,
            'expires_at' => optional($payment->expires_at)->toDateTimeString(),
            'paid_at' => optional($payment->paid_at)->toDateTimeString(),
        ];
    }

    protected function getStoreInformation(array $args, User $actor): array
    {
        $store = Store::find((int) ($args['store_id'] ?? 0));

        if (!$store) {
            return ['error' => 'Informasi tidak tersedia: store tidak ditemukan.'];
        }

        if (!$this->storeVisibleTo($store, $actor)) {
            return ['error' => 'Informasi tidak tersedia: store tidak dapat diakses.'];
        }

        return [
            'name' => $store->name,
            'city' => $store->city,
            'address' => $store->address,
            'status' => $store->status,
            'is_active' => $store->is_active,
            'average_rating' => $store->average_rating !== null ? (string) $store->average_rating : null,
            'review_count' => (int) $store->review_count,
        ];
    }

    protected function getStoreService(array $args, User $actor): array
    {
        $store = Store::find((int) ($args['store_id'] ?? 0));

        if (!$store) {
            return ['error' => 'Informasi tidak tersedia: store tidak ditemukan.'];
        }

        if (!$this->storeVisibleTo($store, $actor)) {
            return ['error' => 'Informasi tidak tersedia: store tidak dapat diakses.'];
        }

        $services = ServicePrice::where('store_id', $store->id)
            ->where('is_active', true)
            ->with('service:id,name')
            ->get()
            ->map(fn ($sp) => [
                'service_name' => optional($sp->service)->name,
                'price_per_unit' => (string) $sp->price_per_unit,
                'unit' => $sp->unit,
            ])
            ->values()
            ->all();

        return [
            'store_name' => $store->name,
            'services' => $services,
        ];
    }

    protected function getOrderBasicInformation(array $args, User $actor): array
    {
        $order = Order::find((int) ($args['order_id'] ?? 0));

        if (!$order) {
            return ['error' => 'Informasi tidak tersedia: order tidak ditemukan.'];
        }

        // Authorization: only the order's customer, or the store's owner/staff.
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

    // ========== AUTHORIZATION HELPERS ==========

    protected function storeVisibleTo(Store $store, User $actor): bool
    {
        // Public data only for approved + active stores...
        if ($store->status === 'approved' && $store->is_active) {
            return true;
        }

        // ...otherwise only the owner or active staff may see it.
        return $this->userHasStoreAccess($actor, $store->id);
    }

    protected function userHasStoreAccess(User $user, int $storeId): bool
    {
        $isOwner = Store::where('id', $storeId)->where('owner_user_id', $user->id)->exists();
        $isStaff = $user->storeStaff()->where('store_id', $storeId)->where('is_active', true)->exists();

        return $isOwner || $isStaff;
    }
}