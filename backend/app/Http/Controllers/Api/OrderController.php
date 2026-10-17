<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\PlatformSetting;
use App\Models\Store;
use App\Models\StoreService;
use App\Services\OrderStateMachine;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected OrderStateMachine $stateMachine
    ) {}

    /**
     * Create a new laundry order with price and address snapshots (FR-ORDER-01..06, US-CUS-05).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
            'pickup_address' => ['required', 'array'],
            'pickup_address.address_text' => ['required', 'string'],
            'pickup_address.latitude' => ['required', 'numeric', 'between:-90,90'],
            'pickup_address.longitude' => ['required', 'numeric', 'between:-180,180'],
            'pickup_address.label' => ['nullable', 'string', 'max:50'],
            'delivery_address' => ['nullable', 'array'],
            'customer_notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['required', 'uuid', 'exists:store_services,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:1000.0'],
        ], [
            'store_id.required' => 'Toko laundry wajib dipilih.',
            'pickup_address.required' => 'Alamat penjemputan cucian wajib disertakan.',
            'pickup_address.address_text.required' => 'Alamat lengkap penjemputan wajib diisi.',
            'pickup_address.latitude.required' => 'Titik koordinat latitude penjemputan wajib diisi.',
            'pickup_address.longitude.required' => 'Titik koordinat longitude penjemputan wajib diisi.',
            'items.required' => 'Minimal satu layanan harus dipilih.',
            'items.*.service_id.required' => 'ID layanan pada item wajib diisi.',
            'items.*.quantity.required' => 'Estimasi kuantitas item wajib diisi.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'VALIDATION_ERROR',
                'Input pembuatan pesanan tidak valid.',
                $validator->errors()->toArray(),
                422
            );
        }

        $validated = $validator->validated();

        $store = Store::where('id', $validated['store_id'])
            ->where('approval_status', 'approved')
            ->where('operational_status', 'active')
            ->first();

        if (!$store) {
            return $this->errorResponse(
                'STORE_NOT_AVAILABLE',
                'Toko tidak tersedia atau sedang tidak beroperasi.',
                null,
                404
            );
        }

        // Backend re-validation of service radius (Haversine distance check)
        $pickupLat = (float) $validated['pickup_address']['latitude'];
        $pickupLng = (float) $validated['pickup_address']['longitude'];

        if ($store->latitude !== null && $store->longitude !== null) {
            $distanceKm = $this->calculateHaversineDistance(
                $pickupLat,
                $pickupLng,
                (float) $store->latitude,
                (float) $store->longitude
            );

            if ($distanceKm > (float) $store->service_radius_km) {
                return $this->errorResponse(
                    'STORE_OUT_OF_RADIUS',
                    "Alamat penjemputan ({$distanceKm} km) berada di luar radius layanan toko ini (maks {$store->service_radius_km} km).",
                    [
                        'distance_km' => $distanceKm,
                        'service_radius_km' => (float) $store->service_radius_km,
                    ],
                    422
                );
            }
        }

        // Process item snapshots and calculate total estimated amount
        $order = DB::transaction(function () use ($user, $store, $validated) {
            $orderNumber = $this->generateUniqueOrderNumber();
            $pickupSnapshot = $validated['pickup_address'];
            $deliverySnapshot = $validated['delivery_address'] ?? $validated['pickup_address'];

            $order = Order::create([
                'order_number' => $orderNumber,
                'customer_id' => $user->id,
                'store_id' => $store->id,
                'courier_id' => null,
                'pickup_address_snapshot' => $pickupSnapshot,
                'delivery_address_snapshot' => $deliverySnapshot,
                'status' => 'PENDING_PICKUP',
                'payment_status' => 'UNPAID',
                'estimated_weight_kg' => null,
                'estimated_amount' => 0,
                'final_amount' => null,
                'delivery_fee' => 0,
                'commission_amount' => PlatformSetting::getPlatformCommission(), // Dynamic platform commission snapshot
                'courier_wage' => 0,
                'customer_notes' => $validated['customer_notes'] ?? null,
            ]);

            $totalEstimatedAmount = 0;
            $totalEstimatedKg = 0;

            foreach ($validated['items'] as $item) {
                $service = StoreService::where('id', $item['service_id'])
                    ->where('store_id', $store->id)
                    ->first();

                if (!$service || !$service->is_active) {
                    throw new \InvalidArgumentException("Layanan '{$item['service_id']}' tidak aktif atau tidak ditemukan pada toko ini.");
                }

                $quantity = (float) $item['quantity'];
                $unitPrice = (int) $service->price_per_unit;
                $minimumCharge = (int) $service->minimum_charge;

                $rawLineEstimated = (int) round($quantity * $unitPrice);
                $lineEstimated = $rawLineEstimated;

                if ($minimumCharge > 0 && $rawLineEstimated < $minimumCharge) {
                    $lineEstimated = $minimumCharge;
                }

                if ($service->pricing_unit === 'kg') {
                    $totalEstimatedKg += $quantity;
                }

                $totalEstimatedAmount += $lineEstimated;

                OrderItem::create([
                    'order_id' => $order->id,
                    'service_id' => $service->id,
                    'service_name_snapshot' => $service->name,
                    'pricing_unit_snapshot' => $service->pricing_unit,
                    'unit_price_snapshot' => $unitPrice,
                    'minimum_charge_snapshot' => $minimumCharge,
                    'quantity_estimated' => $quantity,
                    'quantity_actual' => null,
                    'line_estimated_amount' => $lineEstimated,
                    'line_final_amount' => null,
                ]);
            }

            $order->estimated_amount = $totalEstimatedAmount;
            $order->estimated_weight_kg = $totalEstimatedKg > 0 ? $totalEstimatedKg : null;
            $order->save();

            // Record initial status history event
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => 'PENDING_PICKUP',
                'actor_user_id' => $user->id,
                'actor_role' => 'customer',
                'note' => 'Pesanan berhasil dibuat oleh pelanggan.',
                'created_at' => now(),
            ]);

            \App\Services\AuditLogService::logOrder($order, 'ORDER_CREATED', $user, [
                'items_count' => count($validated['items']),
                'estimated_amount' => $totalEstimatedAmount,
            ]);

            return $order;
        });

        $order->load(['items', 'store', 'customer']);

        // Dispatch Order Created multi-channel notifications safely (FR-NOTIF)
        try {
            app(\App\Services\Notification\NotificationService::class)->notifyOrderCreated($order);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send order created notifications: {$e->getMessage()}");
        }

        return $this->successResponse(
            data: $this->formatOrder($order),
            meta: [
                'message' => 'Pesanan berhasil dibuat. Menunggu penugasan kurir jemput.',
            ],
            status: 201
        );
    }

    /**
     * List orders for the authenticated actor (FR-ORDER-07, FR-ORDER-08).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Order::query()->with(['items', 'store', 'customer', 'courier', 'payments']);

        // Role-based scoping
        if ($user->hasRole('super_admin')) {
            // Super admin sees all orders
        } elseif ($user->hasRole('store_admin') && $request->filled('store_id')) {
            $storeId = $request->query('store_id');
            $query->where('store_id', $storeId);
        } elseif ($user->hasRole('store_admin')) {
            $storeIds = Store::where('owner_user_id', $user->id)->pluck('id');
            if ($storeIds->isNotEmpty()) {
                $query->whereIn('store_id', $storeIds);
            } else {
                $query->where('customer_id', $user->id);
            }
        } elseif ($user->hasRole('courier')) {
            $query->where('courier_id', $user->id);
        } else {
            // Default: Customer sees their own orders
            $query->where('customer_id', $user->id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->query('status')));
        }

        $perPage = (int) $request->query('per_page', 10);
        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $items = collect($orders->items())->map(fn ($o) => $this->formatOrder($o));

        return $this->successResponse(
            data: $items,
            meta: [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        );
    }

    /**
     * Get order detail (FR-ORDER-07, FR-ORDER-08).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $order = Order::with(['items', 'store', 'customer', 'courier', 'statusHistories.actor', 'assignments.courier'])->find($id);

        if (!$order) {
            return $this->errorResponse('NOT_FOUND', 'Pesanan tidak ditemukan.', null, 404);
        }

        $this->authorizeOrderAccess($order, $user);

        return $this->successResponse(
            data: $this->formatOrder($order, true)
        );
    }

    /**
     * Get order status tracking timeline (FR-TRACK-01, FR-TRACK-02).
     */
    public function tracking(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $order = Order::with(['statusHistories.actor', 'store', 'courier'])->find($id);

        if (!$order) {
            return $this->errorResponse('NOT_FOUND', 'Pesanan tidak ditemukan.', null, 404);
        }

        $this->authorizeOrderAccess($order, $user);

        $timeline = $order->statusHistories->map(function ($hist) {
            return [
                'id' => $hist->id,
                'from_status' => $hist->from_status,
                'to_status' => $hist->to_status,
                'actor_role' => $hist->actor_role,
                'actor_name' => $hist->actor?->name,
                'note' => $hist->note,
                'timestamp' => $hist->created_at?->toIso8601String(),
            ];
        });

        return $this->successResponse(
            data: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'current_status' => $order->status,
                'payment_status' => $order->payment_status,
                'store_name' => $order->store?->name,
                'courier_name' => $order->courier?->name,
                'timeline' => $timeline,
            ]
        );
    }

    /**
     * Execute state machine status transition (PRD 7.3 & 7.4).
     */
    public function transition(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $order = Order::with(['items', 'store'])->find($id);

        if (!$order) {
            return $this->errorResponse('NOT_FOUND', 'Pesanan tidak ditemukan.', null, 404);
        }

        $this->authorizeOrderAccess($order, $user);

        $toStatus = $request->input('to_status') ?? $request->input('target_status');
        if (empty($toStatus)) {
            return $this->errorResponse('VALIDATION_ERROR', 'Target status pesanan (to_status) wajib diisi.', null, 422);
        }

        $payload = $request->all();
        $note = $request->input('note') ?? $request->input('notes') ?? $request->input('reason');

        $updatedOrder = $this->stateMachine->transition($order, $toStatus, $user, $payload, $note);

        return $this->successResponse(
            data: $this->formatOrder($updatedOrder, true),
            meta: [
                'message' => "Status pesanan berhasil diperbarui ke '{$toStatus}'.",
            ]
        );
    }

    /**
     * Cancel order (FR-ORDER-11, US-CUS-07).
     * ONLY valid when status is AWAITING_PRICE_APPROVAL (customer rejects final price).
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $order = Order::with('store')->find($id);

        if (!$order) {
            return $this->errorResponse('NOT_FOUND', 'Pesanan tidak ditemukan.', null, 404);
        }

        // Validate business rule: Customer can ONLY cancel at AWAITING_PRICE_APPROVAL stage
        if ($order->status !== 'AWAITING_PRICE_APPROVAL') {
            return $this->errorResponse(
                'INVALID_STATE_TRANSITION',
                "Pesanan tidak dapat dibatalkan pada status '{$order->status}'. Pembatalan oleh pelanggan hanya diizinkan pada tahap persetujuan harga (AWAITING_PRICE_APPROVAL).",
                [
                    'current_status' => $order->status,
                    'allowed_cancel_status' => 'AWAITING_PRICE_APPROVAL',
                ],
                409
            );
        }

        $note = $request->input('note') ?? $request->input('notes') ?? $request->input('reason') ?? 'Pelanggan menolak harga final setelah penimbangan.';
        $updatedOrder = $this->stateMachine->transition($order, 'CANCELLED', $user, [], $note);

        return $this->successResponse(
            data: $this->formatOrder($updatedOrder, true),
            meta: [
                'message' => 'Pesanan berhasil dibatalkan pada tahap persetujuan harga.',
            ]
        );
    }

    /**
     * Customer Price Approval or Rejection (US-CUS-07, FR-PRICE-05, FR-PRICE-06).
     * Accepts: action = 'approve' | 'reject', reason = optional string
     */
    public function priceApproval(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $order = Order::with(['items', 'store'])->find($id);

        if (!$order) {
            return $this->errorResponse('NOT_FOUND', 'Pesanan tidak ditemukan.', null, 404);
        }

        if ($order->customer_id !== $user->id && !$user->hasRole('super_admin')) {
            return $this->errorResponse('FORBIDDEN', 'Hanya pelanggan pemilik pesanan yang dapat menyetujui atau menolak harga final.', null, 403);
        }

        if ($order->status !== 'AWAITING_PRICE_APPROVAL') {
            return $this->errorResponse(
                'INVALID_STATE_TRANSITION',
                "Persetujuan harga hanya dapat dilakukan saat pesanan berstatus 'AWAITING_PRICE_APPROVAL'. Status saat ini: '{$order->status}'.",
                [
                    'current_status' => $order->status,
                    'required_status' => 'AWAITING_PRICE_APPROVAL',
                ],
                409
            );
        }

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:approve,reject,APPROVE,REJECT'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'action.required' => 'Aksi persetujuan (action: approve/reject) wajib ditentukan.',
            'action.in' => 'Aksi hanya boleh bernilai "approve" atau "reject".',
        ]);

        $action = strtolower($validated['action']);
        $reason = $validated['reason'] ?? null;

        if ($action === 'reject') {
            // Customer rejects price -> Order moves to CANCELLED
            $updated = $this->stateMachine->transition(
                $order,
                'CANCELLED',
                $user,
                ['cancellation_reason' => $reason],
                $reason ?: 'Pelanggan menolak harga final hasil penimbangan cucian.'
            );

            return $this->successResponse(
                data: $this->formatOrder($updated, true),
                meta: ['message' => 'Harga final ditolak. Pesanan berhasil dibatalkan dan cucian akan dikembalikan.']
            );
        }

        // Customer approves price -> Order moves to PRICE_APPROVED, then auto advances to AWAITING_PAYMENT
        $updated = $this->stateMachine->transition(
            $order,
            'PRICE_APPROVED',
            $user,
            [],
            'Pelanggan menyetujui rincian harga final.'
        );

        // Auto transition to AWAITING_PAYMENT
        $updated = $this->stateMachine->transition(
            $updated,
            'AWAITING_PAYMENT',
            $user,
            ['is_system' => true],
            'Menunggu pembayaran dari pelanggan.'
        );

        return $this->successResponse(
            data: $this->formatOrder($updated, true),
            meta: ['message' => 'Harga final disetujui. Silakan selesaikan pembayaran untuk memulai pencucian.']
        );
    }

    /**
     * Helper to verify if user has permission to view order.
     */
    protected function authorizeOrderAccess(Order $order, $user): void
    {
        if ($user->hasRole('super_admin')) return;
        if ($order->customer_id === $user->id) return;
        if ($order->store && $order->store->owner_user_id === $user->id) return;
        if ($order->courier_id === $user->id) return;

        abort(response()->json([
            'success' => false,
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Anda tidak memiliki hak akses untuk melihat pesanan ini.',
                'details' => (object)[],
            ],
        ], 403));
    }

    /**
     * Helper to generate unique order number: ORD-YYYYMMDD-XXXX
     */
    protected function generateUniqueOrderNumber(): string
    {
        $datePrefix = 'ORD-' . date('Ymd') . '-';
        do {
            $randomCode = strtoupper(Str::random(4));
            $orderNumber = $datePrefix . $randomCode;
        } while (Order::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    /**
     * Helper to calculate Haversine distance in km.
     */
    protected function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c, 2);
    }

    /**
     * Helper to format Order into clean response structure.
     */
    protected function formatOrder(Order $order, bool $includeDetails = false): array
    {
        $response = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_id' => $order->customer_id,
            'customer_name' => $order->customer?->name,
            'store_id' => $order->store_id,
            'store_name' => $order->store?->name,
            'courier_id' => $order->courier_id,
            'courier_name' => $order->courier?->name,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'estimated_weight_kg' => $order->estimated_weight_kg !== null ? (float) $order->estimated_weight_kg : null,
            'actual_weight_kg' => $order->actual_weight_kg !== null ? (float) $order->actual_weight_kg : null,
            'estimated_amount' => (int) $order->estimated_amount,
            'final_amount' => $order->final_amount !== null ? (int) $order->final_amount : null,
            'delivery_fee' => (int) $order->delivery_fee,
            'commission_amount' => (int) $order->commission_amount,
            'pickup_address' => $order->pickup_address_snapshot,
            'delivery_address' => $order->delivery_address_snapshot,
            'customer_notes' => $order->customer_notes,
            'price_approved_at' => $order->price_approved_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
            'payment_expires_at' => $this->resolvePaymentExpiresAt($order),
        ];

        if ($order->relationLoaded('items')) {
            $response['items'] = $order->items->map(fn ($item) => [
                'id' => $item->id,
                'service_id' => $item->service_id,
                'service_name' => $item->service_name_snapshot,
                'pricing_unit' => $item->pricing_unit_snapshot,
                'unit_price' => (int) $item->unit_price_snapshot,
                'minimum_charge' => (int) $item->minimum_charge_snapshot,
                'quantity_estimated' => (float) $item->quantity_estimated,
                'quantity_actual' => $item->quantity_actual !== null ? (float) $item->quantity_actual : null,
                'line_estimated_amount' => (int) $item->line_estimated_amount,
                'line_final_amount' => $item->line_final_amount !== null ? (int) $item->line_final_amount : null,
            ]);
        }

        if ($includeDetails && $order->relationLoaded('statusHistories')) {
            $response['status_histories'] = $order->statusHistories->map(fn ($h) => [
                'id' => $h->id,
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'actor_role' => $h->actor_role,
                'actor_name' => $h->actor?->name,
                'note' => $h->note,
                'timestamp' => $h->created_at?->toIso8601String(),
            ]);
        }

        return $response;
    }

    /**
     * Resolve the payment expiration ISO timestamp for an order.
     */
    protected function resolvePaymentExpiresAt(Order $order): ?string
    {
        if ($order->status !== 'AWAITING_PAYMENT') {
            return null;
        }

        $latestPayment = $order->relationLoaded('payments')
            ? $order->payments->first()
            : \App\Models\Payment::where('order_id', $order->id)->latest()->first();

        if ($latestPayment && is_array($latestPayment->payment_details)) {
            $rawExpiry = $latestPayment->payment_details['expiration_date'] ?? $latestPayment->payment_details['expires_at'] ?? null;
            if ($rawExpiry) {
                return (string) $rawExpiry;
            }
        }

        $baseTime = $order->price_approved_at ?: $order->updated_at ?: $order->created_at;
        $fallbackMinutes = (int) config('payment.expiry_minutes', 1440);
        return $baseTime ? (clone $baseTime)->addMinutes($fallbackMinutes)->toIso8601String() : null;
    }
}
