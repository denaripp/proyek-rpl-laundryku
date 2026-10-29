<?php

namespace App\Http\Controllers\Api\Portal;

use App\Enums\OrderStatus;
use App\Enums\CourierTaskStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Store;
use App\Notifications\NotificationTrigger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourierPortalController extends Controller
{
    /**
     * Resolve courier record for authenticated user.
     */
    protected function resolveCourier(Request $request): ?Courier
    {
        return Courier::with(['user:id,name,email,phone', 'store'])->where('user_id', $request->user()->id)->first();
    }

    /**
     * Format timestamp with Asia/Jakarta timezone.
     */
    protected function formatTimestamp(?string $raw, $fallback = null): ?string
    {
        if ($raw) {
            return Carbon::parse($raw, 'Asia/Jakarta')->toIso8601String();
        }
        return $fallback ? Carbon::parse($fallback)->setTimezone('Asia/Jakarta')->toIso8601String() : null;
    }

    /**
     * Format an order/task as canonical CourierTaskItem.
     */
    protected function formatCourierTask(Order $order, ?CourierTask $task, Courier $courier): array
    {
        $isPickup = in_array(strtolower($order->status), [
            'created', 'waiting_pickup', 'pending_pickup', 'waiting_for_courier',
            'courier_assigned', 'pickup_assigned', 'courier_to_customer', 'picked_up', 'in_transit_to_store',
        ], true);

        $currentPhaseType = $isPickup ? 'pickup' : 'delivery';
        $currentTask = ($task && $task->task_type === $currentPhaseType)
            ? $task
            : ($order->courierTasks?->firstWhere('task_type', $currentPhaseType) ?? $task);

        $taskType = $currentTask?->task_type ?? $currentPhaseType;

        $isDeliveryWaiting = strtolower($order->status) === OrderStatus::READY_FOR_DELIVERY->value;

        $isAssignedToMe = ! $isDeliveryWaiting && (
            ((int) $order->courier_id === (int) $courier->user_id) ||
            ((int) $order->courier_id === (int) $courier->id) ||
            ($currentTask && (int) $currentTask->courier_id === (int) $courier->id && !in_array($currentTask->status, [CourierTaskStatus::CANCELLED->value, CourierTaskStatus::FAILED->value], true))
        );

        $effectiveCourierId = null;
        if (! $isDeliveryWaiting) {
            $effectiveCourierId = $order->courier_id ?? $currentTask?->courier?->user_id ?? $currentTask?->courier_id;
        }

        $pickupAddress = is_array($order->pickup_address) ? $order->pickup_address : [
            'label' => 'Alamat Jemput',
            'address_text' => is_string($order->pickup_address) ? $order->pickup_address : '',
            'latitude' => (float) ($order->pickup_latitude ?? 0),
            'longitude' => (float) ($order->pickup_longitude ?? 0),
        ];

        $deliveryAddress = is_array($order->delivery_address) ? $order->delivery_address : [
            'label' => 'Alamat Antar',
            'address_text' => is_string($order->delivery_address) ? $order->delivery_address : '',
            'latitude' => (float) ($order->delivery_latitude ?? 0),
            'longitude' => (float) ($order->delivery_longitude ?? 0),
        ];

        $rawCreated = $order->getRawOriginal('created_at');
        $rawUpdated = $order->getRawOriginal('updated_at');

        $statusMap = [
            OrderStatus::CREATED->value => 'PENDING_PICKUP',
            OrderStatus::WAITING_PICKUP->value => 'PENDING_PICKUP',
            'pending_pickup' => 'PENDING_PICKUP',
            'waiting_for_courier' => 'PENDING_PICKUP',
            OrderStatus::COURIER_ASSIGNED->value => 'PICKUP_ASSIGNED',
            'pickup_assigned' => 'PICKUP_ASSIGNED',
            OrderStatus::COURIER_TO_CUSTOMER->value => 'PICKUP_ASSIGNED',
            OrderStatus::PICKED_UP->value => 'PICKED_UP',
            OrderStatus::IN_TRANSIT_TO_STORE->value => 'PICKED_UP',
            'at_store' => 'AT_STORE',
            'received' => 'AT_STORE',
            OrderStatus::RECEIVED_BY_STORE->value => 'AT_STORE',
            OrderStatus::WEIGHED->value => 'WEIGHED',
            'awaiting_price_approval' => 'AWAITING_PRICE_APPROVAL',
            'price_approved' => 'PRICE_APPROVED',
            'awaiting_payment' => 'AWAITING_PAYMENT',
            OrderStatus::PAID->value => 'PAID',
            OrderStatus::PROCESSING->value => 'PROCESSING',
            OrderStatus::READY_FOR_DELIVERY->value => 'READY_FOR_DELIVERY',
            OrderStatus::COURIER_ASSIGNED_FOR_DELIVERY->value => 'DELIVERY_ASSIGNED',
            'delivery_assigned' => 'DELIVERY_ASSIGNED',
            OrderStatus::OUT_FOR_DELIVERY->value => 'OUT_FOR_DELIVERY',
            OrderStatus::COMPLETED->value => 'COMPLETED',
            OrderStatus::DELIVERED->value => 'COMPLETED',
            OrderStatus::CANCELLED->value => 'CANCELLED',
        ];

        $paymentStatusMap = [
            'unpaid' => 'UNPAID',
            'pending' => 'UNPAID',
            'paid' => 'PAID',
            'failed' => 'PAYMENT_FAILED',
            'expired' => 'PAYMENT_EXPIRED',
        ];

        return [
            'id' => (string) ($task?->id ?? $order->id),
            'order_id' => (string) $order->id,
            'order_number' => $order->order_number,
            'task_type' => $taskType,
            'status' => $statusMap[strtolower($order->status)] ?? strtoupper($order->status),
            'payment_status' => $paymentStatusMap[strtolower($order->payment_status ?? 'unpaid')] ?? strtoupper($order->payment_status ?? 'UNPAID'),
            'is_assigned_to_me' => $isAssignedToMe,
            'courier_id' => $effectiveCourierId ? (string) $effectiveCourierId : null,
            'courier_name' => $order->courier?->name,
            'customer_name' => $order->customer?->name ?? 'Pelanggan',
            'customer_phone' => $order->customer?->phone,
            'store_id' => (string) $order->store_id,
            'store_name' => $order->store?->name,
            'store_address' => $order->store?->address,
            'pickup_address' => $pickupAddress,
            'delivery_address' => $deliveryAddress,
            'estimated_amount' => (float) ($order->estimated_price ?? $order->total_amount ?? 0),
            'final_amount' => $order->final_price !== null ? (float) $order->final_price : null,
            'estimated_weight_kg' => $order->estimated_weight !== null ? (float) $order->estimated_weight : null,
            'actual_weight_kg' => $order->actual_weight !== null ? (float) $order->actual_weight : null,
            'customer_notes' => $order->customer_notes,
            'items_count' => $order->items ? $order->items->count() : 0,
            'items' => $order->items ? $order->items->map(function ($it) {
                return [
                    'id' => (string) $it->id,
                    'service_name' => $it->service_name ?? ($it->service?->name ?? 'Layanan'),
                    'pricing_unit' => $it->pricing_unit ?? 'kg',
                    'quantity_estimated' => (float) ($it->quantity_estimated ?? $it->quantity ?? 0),
                    'quantity_actual' => $it->quantity_actual !== null ? (float) $it->quantity_actual : null,
                ];
            })->values() : [],
            'created_at' => $this->formatTimestamp($rawCreated, $order->created_at),
            'updated_at' => $this->formatTimestamp($rawUpdated, $order->updated_at),
        ];
    }

    /**
     * GET /api/v1/kurir/tasks (legacy backward compatibility)
     */
    public function tasks(Request $request): JsonResponse
    {
        $courier = $this->resolveCourier($request);

        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $tasks = CourierTask::where('courier_id', $courier->id)
            ->with(['order.store:id,name,phone,address', 'order.customer:id,name,phone'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'courier' => [
                'id' => $courier->id,
                'is_available' => $courier->is_available,
            ],
            'tasks' => $tasks,
        ]);
    }

    /**
     * GET /api/v1/courier/profile
     */
    public function profile(Request $request): JsonResponse
    {
        $courier = $this->resolveCourier($request);

        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        if (! $courier->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Akun kurir belum disetujui atau belum diverifikasi.',
            ], 403);
        }

        $store = $courier->store;
        $rawStarted = $courier->getRawOriginal('created_at');

        $availableTasks = $store
            ? Order::where('store_id', $store->id)
                ->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereIn('status', [OrderStatus::CREATED->value, OrderStatus::WAITING_PICKUP->value, 'pending_pickup', 'waiting_for_courier'])
                            ->whereNull('courier_id')
                            ->whereDoesntHave('courierTasks', function ($t) {
                                $t->whereIn('status', CourierTask::ACTIVE_STATUSES);
                            });
                    })->orWhere(function ($sub) {
                        $sub->where('status', OrderStatus::READY_FOR_DELIVERY->value)
                            ->whereDoesntHave('courierTasks', function ($t) {
                                $t->where('task_type', 'delivery')
                                    ->whereIn('status', CourierTask::ACTIVE_STATUSES);
                            });
                    });
                })->count()
            : 0;

        $taskStats = DB::table('courier_tasks')
            ->where('courier_id', $courier->id)
            ->selectRaw("
                COUNT(CASE WHEN status IN ('assigned', 'accepted', 'arrived', 'picked_up') THEN 1 END) as active_tasks,
                COUNT(CASE WHEN status = 'delivered' THEN 1 END) as completed_tasks
            ")->first();

        $activeTasks = (int) ($taskStats->active_tasks ?? 0);
        $completedTasks = (int) ($taskStats->completed_tasks ?? 0);

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $courier->user?->name ?? 'Kurir',
                'email' => $courier->user?->email,
                'phone' => $courier->user?->phone,
                'is_assigned' => (bool) $courier->store_id,
                'courier_profile_id' => (string) $courier->id,
                'vehicle_type' => $courier->vehicle_type ?? 'Motor',
                'verification_status' => $courier->is_verified ? 'verified' : 'unverified',
                'assignment_status' => $courier->store_id ? 'active' : 'unassigned',
                'started_at' => $this->formatTimestamp($rawStarted, $courier->created_at),
                'store' => $store ? [
                    'id' => (string) $store->id,
                    'name' => $store->name,
                    'address_text' => $store->address,
                    'service_radius_km' => (float) ($store->service_radius_km ?? 10),
                ] : null,
                'stats' => [
                    'available_tasks' => $availableTasks,
                    'active_tasks' => $activeTasks,
                    'completed_tasks' => $completedTasks,
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/courier/tasks?tab=available|active|completed|all
     */
    public function taskList(Request $request): JsonResponse
    {
        $courier = $this->resolveCourier($request);

        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        if (! $courier->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Akun kurir belum disetujui atau belum diverifikasi.',
            ], 403);
        }

        $tab = $request->query('tab', 'all');

        $assignedToCourier = function ($q) use ($courier) {
            $q->where(function ($sub) use ($courier) {
                $sub->where('courier_id', $courier->user_id)
                    ->orWhere('courier_id', $courier->id);
            })->orWhereHas('courierTasks', function ($tq) use ($courier) {
                $tq->where('courier_id', $courier->id)
                    ->whereNotIn('status', [CourierTaskStatus::CANCELLED->value, CourierTaskStatus::FAILED->value]);
            });
        };

        $activeStatuses = [
            OrderStatus::COURIER_ASSIGNED->value,
            'pickup_assigned',
            OrderStatus::COURIER_TO_CUSTOMER->value,
            OrderStatus::PICKED_UP->value,
            OrderStatus::IN_TRANSIT_TO_STORE->value,
            OrderStatus::COURIER_ASSIGNED_FOR_DELIVERY->value,
            'delivery_assigned',
            OrderStatus::OUT_FOR_DELIVERY->value,
        ];
        $availablePickupStatuses = [
            OrderStatus::CREATED->value,
            OrderStatus::WAITING_PICKUP->value,
            'pending_pickup',
            'waiting_for_courier',
        ];
        $availableDeliveryStatuses = [OrderStatus::READY_FOR_DELIVERY->value];
        $completedStatuses = [OrderStatus::COMPLETED->value, OrderStatus::DELIVERED->value];

        $query = Order::with(['store:id,name,address', 'customer:id,name,phone', 'items', 'courierTasks']);

        if ($tab === 'available') {
            if (! $courier->store_id) {
                return response()->json(['success' => true, 'data' => []]);
            }
            $query->where('store_id', $courier->store_id)
                ->where(function ($availQuery) use ($availablePickupStatuses, $availableDeliveryStatuses) {
                    $availQuery->where(function ($pq) use ($availablePickupStatuses) {
                        $pq->whereIn('status', $availablePickupStatuses)
                            ->whereNull('courier_id')
                            ->whereDoesntHave('courierTasks', function ($t) {
                                $t->whereIn('status', CourierTask::ACTIVE_STATUSES);
                            });
                    })->orWhere(function ($dq) use ($availableDeliveryStatuses) {
                        $dq->whereIn('status', $availableDeliveryStatuses)
                            ->whereDoesntHave('courierTasks', function ($t) {
                                $t->where('task_type', 'delivery')
                                    ->whereIn('status', CourierTask::ACTIVE_STATUSES);
                            });
                    });
                });
        } elseif ($tab === 'active') {
            $query->where($assignedToCourier)
                ->whereIn('status', $activeStatuses);
        } elseif ($tab === 'completed') {
            $query->where($assignedToCourier)
                ->whereIn('status', $completedStatuses);
        } else {
            // 'all': active + completed for this courier, plus available tasks for courier's store
            $query->where(function ($q) use ($assignedToCourier, $courier, $availablePickupStatuses, $availableDeliveryStatuses) {
                $q->where($assignedToCourier);
                if ($courier->store_id) {
                    $q->orWhere(function ($sub) use ($courier, $availablePickupStatuses, $availableDeliveryStatuses) {
                        $sub->where('store_id', $courier->store_id)
                            ->where(function ($availQuery) use ($availablePickupStatuses, $availableDeliveryStatuses) {
                                $availQuery->where(function ($pq) use ($availablePickupStatuses) {
                                    $pq->whereIn('status', $availablePickupStatuses)
                                        ->whereNull('courier_id')
                                        ->whereDoesntHave('courierTasks', function ($t) {
                                            $t->whereIn('status', CourierTask::ACTIVE_STATUSES);
                                        });
                                })->orWhere(function ($dq) use ($availableDeliveryStatuses) {
                                    $dq->whereIn('status', $availableDeliveryStatuses)
                                        ->whereDoesntHave('courierTasks', function ($t) {
                                            $t->where('task_type', 'delivery')
                                                ->whereIn('status', CourierTask::ACTIVE_STATUSES);
                                        });
                                });
                            });
                    });
                }
            });
        }

        $orders = $query->latest('created_at')->limit(50)->get();

        $items = $orders->map(function ($order) use ($courier) {
            $task = $order->courierTasks->firstWhere('courier_id', $courier->id) ?? $order->courierTasks->first();
            return $this->formatCourierTask($order, $task, $courier);
        })->values();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * GET /api/v1/courier/tasks/{orderId}
     */
    public function taskDetail(Request $request, $orderId): JsonResponse
    {
        $courier = $this->resolveCourier($request);

        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $order = Order::with(['store:id,name,address', 'customer:id,name,phone', 'items', 'courierTasks'])
            ->where('id', $orderId)
            ->where(function ($q) use ($courier) {
                $q->where('courier_id', $courier->user_id)
                    ->orWhere('courier_id', $courier->id)
                    ->orWhereHas('courierTasks', function ($tq) use ($courier) {
                        $tq->where('courier_id', $courier->id)
                            ->whereNotIn('status', ['cancelled', 'failed']);
                    });
                if ($courier->store_id) {
                    $q->orWhere('store_id', $courier->store_id);
                }
            })
            ->first();

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }

        $task = $order->courierTasks->firstWhere('courier_id', $courier->id) ?? $order->courierTasks->first();

        return response()->json([
            'success' => true,
            'data' => $this->formatCourierTask($order, $task, $courier),
        ]);
    }

    /**
     * POST /api/v1/courier/tasks/{orderId}/claim
     */
    public function claimTask(Request $request, $orderId): JsonResponse
    {
        $user = $request->user();
        $courier = $this->resolveCourier($request);

        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        if (! $courier->store_id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terdaftar dan aktif di toko mana pun.',
            ], 403);
        }

        return DB::transaction(function () use ($courier, $orderId, $user, $request) {
            $order = Order::where('id', $orderId)
                ->where('store_id', $courier->store_id)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pesanan tidak ditemukan.',
                ], 404);
            }

            $isDeliveryPhase = in_array(strtolower($order->status), ['ready_for_delivery', 'courier_assigned_for_delivery', 'delivery_assigned', 'out_for_delivery'], true);
            $taskType = $isDeliveryPhase ? 'delivery' : 'pickup';

            if ($taskType === 'pickup') {
                if ($order->courier_id && (int) $order->courier_id !== (int) $courier->user_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tugas ini sudah diambil oleh kurir lain.',
                    ], 409);
                }
            } else {
                $activeDeliveryTask = CourierTask::where('order_id', $order->id)
                    ->where('task_type', 'delivery')
                    ->whereIn('status', CourierTask::ACTIVE_STATUSES)
                    ->first();
                if ($activeDeliveryTask && (int) $activeDeliveryTask->courier_id !== (int) $courier->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tugas ini sudah diambil oleh kurir lain.',
                    ], 409);
                }
            }

            $order->courier_id = $courier->user_id;
            $order->status = ($taskType === 'pickup') ? 'pickup_assigned' : 'delivery_assigned';
            $order->save();

            $task = CourierTask::updateOrCreate(
                ['order_id' => $order->id, 'task_type' => $taskType],
                [
                    'courier_id' => $courier->id,
                    'status' => CourierTaskStatus::ACCEPTED->value,
                    'assigned_at' => now(),
                    'accepted_at' => now(),
                ]
            );

            AuditLog::create([
                'actor_id' => $user->id,
                'actor_role' => $user->role,
                'action' => 'COURIER_TASK_CLAIM',
                'resource_type' => 'order',
                'resource_id' => $order->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'after_value' => json_encode(['task_type' => $taskType, 'status' => $order->status]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tugas pengantaran berhasil diambil.',
                'data' => $this->formatCourierTask($order->fresh(['items', 'store', 'customer']), $task, $courier),
            ]);
        });
    }

    /**
     * POST /api/v1/courier/tasks/{orderId}/transition
     */
    public function transitionTask(Request $request, $orderId): JsonResponse
    {
        $user = $request->user();
        $courier = $this->resolveCourier($request);

        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $order = Order::find($orderId);
        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }

        $isAuthorized = ((int) $order->courier_id === (int) $courier->user_id)
            || ((int) $order->courier_id === (int) $courier->id)
            || CourierTask::where('order_id', $order->id)
                ->where('courier_id', $courier->id)
                ->whereNotIn('status', [CourierTaskStatus::CANCELLED->value, CourierTaskStatus::FAILED->value])
                ->exists();

        if (! $isAuthorized) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk mengubah tugas pesanan ini.',
            ], 403);
        }

        if (! $order->courier_id) {
            $order->courier_id = $courier->user_id;
        }

        $toStatus = strtolower($request->input('to_status', ''));

        // Strict allowed transitions for courier:
        // pickup flow: pickup_assigned -> picked_up -> at_store
        // delivery flow: delivery_assigned -> out_for_delivery -> delivered / completed
        $validTransitions = [
            'pickup_assigned' => [OrderStatus::PICKED_UP->value, OrderStatus::COURIER_TO_CUSTOMER->value],
            OrderStatus::COURIER_ASSIGNED->value => [OrderStatus::PICKED_UP->value, OrderStatus::COURIER_TO_CUSTOMER->value],
            OrderStatus::COURIER_TO_CUSTOMER->value => [OrderStatus::PICKED_UP->value],
            OrderStatus::PICKED_UP->value => ['at_store', OrderStatus::IN_TRANSIT_TO_STORE->value],
            OrderStatus::IN_TRANSIT_TO_STORE->value => ['at_store'],
            OrderStatus::COURIER_ASSIGNED_FOR_DELIVERY->value => [OrderStatus::OUT_FOR_DELIVERY->value],
            'delivery_assigned' => [OrderStatus::OUT_FOR_DELIVERY->value],
            OrderStatus::OUT_FOR_DELIVERY->value => [OrderStatus::COMPLETED->value, OrderStatus::DELIVERED->value],
        ];

        $currentStatus = strtolower($order->status);
        $allowedNextStatuses = $validTransitions[$currentStatus] ?? [];

        if (! in_array($toStatus, $allowedNextStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => "Lompatan status tidak valid dari '{$currentStatus}' ke '{$toStatus}'.",
            ], 422);
        }

        // Determine whether this transition belongs to delivery or pickup
        $isDeliveryTransition = in_array($toStatus, [OrderStatus::OUT_FOR_DELIVERY->value, OrderStatus::DELIVERED->value, OrderStatus::COMPLETED->value], true);
        $expectedTaskType = $isDeliveryTransition ? 'delivery' : 'pickup';

        // Find the active task for this courier and task type
        $task = CourierTask::where('order_id', $order->id)
            ->where('courier_id', $courier->id)
            ->where('task_type', $expectedTaskType)
            ->whereNotIn('status', [CourierTaskStatus::DELIVERED->value, CourierTaskStatus::FAILED->value, CourierTaskStatus::CANCELLED->value])
            ->latest()
            ->first();

        // Fallback to latest task of expected type for this courier
        if (! $task) {
            $task = CourierTask::where('order_id', $order->id)
                ->where('courier_id', $courier->id)
                ->where('task_type', $expectedTaskType)
                ->latest()
                ->first();
        }

        // Generic fallback to any latest task for this order and courier
        if (! $task) {
            $task = CourierTask::where('order_id', $order->id)
                ->where('courier_id', $courier->id)
                ->latest()
                ->first();
        }

        $effectiveOrderStatus = ($toStatus === OrderStatus::DELIVERED->value) ? OrderStatus::COMPLETED->value : $toStatus;
        $order->status = $effectiveOrderStatus;
        if ($effectiveOrderStatus === OrderStatus::COMPLETED->value) {
            $order->completed_at = now();
        }
        $order->save();

        OrderStatusHistory::record(
            order: $order,
            status: $effectiveOrderStatus,
            previousStatus: $currentStatus,
            actor: $user,
            reason: 'Courier task transition to ' . $effectiveOrderStatus,
            metadata: [
                'task_id' => $task?->id,
                'requested_status' => $toStatus,
            ]
        );

        if ($task) {
            $taskStatusMap = [
                OrderStatus::COURIER_TO_CUSTOMER->value => CourierTaskStatus::ACCEPTED->value,
                OrderStatus::PICKED_UP->value => CourierTaskStatus::PICKED_UP->value,
                OrderStatus::IN_TRANSIT_TO_STORE->value => CourierTaskStatus::PICKED_UP->value,
                'at_store' => CourierTaskStatus::DELIVERED->value,
                OrderStatus::OUT_FOR_DELIVERY->value => CourierTaskStatus::ACCEPTED->value,
                OrderStatus::COMPLETED->value => CourierTaskStatus::DELIVERED->value,
                OrderStatus::DELIVERED->value => CourierTaskStatus::DELIVERED->value,
            ];
            $task->status = $taskStatusMap[$toStatus] ?? $task->status;
            if (in_array($toStatus, ['at_store', OrderStatus::COMPLETED->value, OrderStatus::DELIVERED->value], true)) {
                $taskType = $task->task_type ?? ($toStatus === 'at_store' ? 'pickup' : 'delivery');
                CourierTask::closeActiveFor($order, $taskType);
            } else {
                $task->save();
            }
        }

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'COURIER_TASK_TRANSITION',
            'resource_type' => 'order',
            'resource_id' => $order->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'after_value' => json_encode(['to_status' => $toStatus, 'note' => $request->input('note')]),
        ]);

        NotificationTrigger::courierTaskTransitioned($order, $toStatus);

        return response()->json([
            'success' => true,
            'message' => 'Status tugas berhasil diperbarui.',
            'data' => $this->formatCourierTask($order->fresh(['items', 'store', 'customer']), $task, $courier),
        ]);
    }

    /**
     * POST /api/v1/courier/tasks/{orderId}/confirm-cod
     */
    public function confirmCod(Request $request, $orderId): JsonResponse
    {
        $user = $request->user();
        $courier = $this->resolveCourier($request);

        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $order = Order::find($orderId);
        if (! $order || (int) $order->courier_id !== (int) $courier->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan atau tidak ditugaskan kepada Anda.',
            ], 404);
        }

        $order->payment_status = 'paid';
        $order->save();

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'COURIER_CONFIRM_COD',
            'resource_type' => 'order',
            'resource_id' => $order->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'after_value' => json_encode(['payment_status' => 'paid', 'amount' => $order->total_amount]),
        ]);

        NotificationTrigger::codPaymentReceived($order);

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran COD pesanan berhasil dikonfirmasi.',
            'data' => [
                'order_id' => (string) $order->id,
                'order_number' => $order->order_number,
                'payment_status' => 'PAID',
                'collected_amount' => (float) ($order->total_amount ?? $order->final_price ?? 0),
                'collected_by' => $user->name,
            ],
        ]);
    }
}
