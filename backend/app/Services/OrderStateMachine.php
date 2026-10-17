<?php

namespace App\Services;

use App\Exceptions\InvalidOrderTransitionException;
use App\Models\CourierStoreAssignment;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\DB;

class OrderStateMachine
{
    /**
     * State transition matrix conforming to PRD 7.3 & 7.4.
     * Maps [from_status][to_status] => [allowed_roles, handler_method]
     */
    protected const TRANSITION_MATRIX = [
        'PENDING_PICKUP' => [
            'PICKUP_ASSIGNED' => ['roles' => ['courier', 'store_admin', 'super_admin']],
        ],
        'PICKUP_ASSIGNED' => [
            'PICKED_UP' => ['roles' => ['courier', 'super_admin']],
        ],
        'PICKED_UP' => [
            'AT_STORE' => ['roles' => ['courier', 'store_admin', 'super_admin']],
        ],
        'AT_STORE' => [
            'WEIGHED' => ['roles' => ['store_admin', 'super_admin']],
        ],
        'WEIGHED' => [
            'AWAITING_PRICE_APPROVAL' => ['roles' => ['store_admin', 'super_admin', 'system']],
        ],
        'AWAITING_PRICE_APPROVAL' => [
            'PRICE_APPROVED' => ['roles' => ['customer', 'super_admin']],
            'CANCELLED' => ['roles' => ['customer', 'super_admin']],
        ],
        'PRICE_APPROVED' => [
            'AWAITING_PAYMENT' => ['roles' => ['customer', 'store_admin', 'super_admin', 'system']],
        ],
        'AWAITING_PAYMENT' => [
            'PAID' => ['roles' => ['store_admin', 'super_admin', 'system', 'customer']],
            'PAYMENT_FAILED' => ['roles' => ['super_admin', 'system']],
            'PAYMENT_EXPIRED' => ['roles' => ['super_admin', 'system']],
        ],
        'PAID' => [
            'PROCESSING' => ['roles' => ['store_admin', 'super_admin']],
        ],
        'PROCESSING' => [
            'READY_FOR_DELIVERY' => ['roles' => ['store_admin', 'super_admin']],
        ],
        'READY_FOR_DELIVERY' => [
            'DELIVERY_ASSIGNED' => ['roles' => ['courier', 'store_admin', 'super_admin']],
        ],
        'DELIVERY_ASSIGNED' => [
            'OUT_FOR_DELIVERY' => ['roles' => ['courier', 'super_admin']],
        ],
        'OUT_FOR_DELIVERY' => [
            'COMPLETED' => ['roles' => ['courier', 'store_admin', 'super_admin']],
        ],
    ];

    /**
     * Validate and execute status transition on an order.
     *
     * @throws InvalidOrderTransitionException
     */
    public function transition(
        Order $order,
        string $toStatus,
        User $actor,
        array $payload = [],
        ?string $note = null
    ): Order {
        $fromStatus = $order->status;
        $toStatus = strtoupper(trim($toStatus));

        // 1. Validate if transition exists in matrix
        if (!isset(self::TRANSITION_MATRIX[$fromStatus][$toStatus])) {
            throw new InvalidOrderTransitionException(
                message: "Transisi status dari '{$fromStatus}' ke '{$toStatus}' tidak diizinkan oleh sistem.",
                fromStatus: $fromStatus,
                toStatus: $toStatus
            );
        }

        $rule = self::TRANSITION_MATRIX[$fromStatus][$toStatus];
        $allowedRoles = $rule['roles'];

        // 2. Determine actor role and check tenant / assignment authorization
        $actorRole = $this->resolveAndAuthorizeActorRole($order, $actor, $fromStatus, $toStatus, $allowedRoles, $payload);

        // 3. Execute transition inside a database transaction
        $updatedOrder = DB::transaction(function () use ($order, $fromStatus, $toStatus, $actor, $actorRole, $payload, $note) {
            // Apply side effects per transition
            $this->applyTransitionSideEffects($order, $fromStatus, $toStatus, $payload);

            // Update main status
            $order->status = $toStatus;
            $order->save();

            // Record status history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'actor_user_id' => $actor->id,
                'actor_role' => $actorRole,
                'note' => $note,
                'created_at' => now(),
            ]);

            \App\Services\AuditLogService::logOrder(
                $order,
                "ORDER_TRANSITION_{$toStatus}",
                $actor,
                ['from_status' => $fromStatus, 'to_status' => $toStatus, 'note' => $note]
            );

            return $order->fresh(['items', 'statusHistories', 'assignments', 'customer', 'store', 'courier']);
        });

        // 4. Dispatch multi-channel notifications safely (FR-NOTIF)
        try {
            /** @var \App\Services\Notification\NotificationService $notifier */
            $notifier = app(\App\Services\Notification\NotificationService::class);
            $notifier->notifyOrderStatusChanged($updatedOrder, $fromStatus, $toStatus, $note);

            if ($toStatus === 'AWAITING_PRICE_APPROVAL') {
                $notifier->notifyPriceEstimated($updatedOrder);
            } elseif ($toStatus === 'COMPLETED') {
                $notifier->notifyOrderCompleted($updatedOrder);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send status transition notifications: {$e->getMessage()}");
        }

        return $updatedOrder;
    }

    /**
     * Resolve role of actor and verify tenant and assignment permissions.
     */
    protected function resolveAndAuthorizeActorRole(
        Order $order,
        User $actor,
        string $fromStatus,
        string $toStatus,
        array $allowedRoles,
        array $payload
    ): string {
        // Super Admin override
        if ($actor->hasRole('super_admin')) {
            return 'super_admin';
        }

        // Customer Check
        if (in_array('customer', $allowedRoles) && $order->customer_id === $actor->id) {
            return 'customer';
        }

        // Store Admin Check (Must be the verified owner of the order's store)
        if (in_array('store_admin', $allowedRoles)) {
            $store = Store::find($order->store_id);
            $isStoreOwner = $store && (string) $store->owner_user_id === (string) $actor->id;

            if ($isStoreOwner) {
                return 'store_admin';
            }
        }

        // Courier Check (FR-COURIER-03, FR-COURIER-06: Courier bound to single store)
        if (in_array('courier', $allowedRoles)) {
            $isAssignedCourier = (string) $order->courier_id === (string) $actor->id;

            $hasActiveStoreAssignment = CourierStoreAssignment::whereHas('courier', fn ($q) => $q->where('user_id', $actor->id))
                ->where('store_id', $order->store_id)
                ->where('status', 'active')
                ->exists();

            $hasCourierRole = $actor->hasRole('courier') || UserRole::where('user_id', $actor->id)
                ->where('status', 'active')
                ->whereHas('role', fn ($q) => $q->where('code', 'courier'))
                ->exists();

            if ($isAssignedCourier || $hasActiveStoreAssignment || $actor->hasRole('super_admin')) {
                $canClaim = ($fromStatus === 'PENDING_PICKUP' && $toStatus === 'PICKUP_ASSIGNED') ||
                            ($fromStatus === 'READY_FOR_DELIVERY' && $toStatus === 'DELIVERY_ASSIGNED');

                if ($isAssignedCourier || $canClaim || $hasCourierRole) {
                    return 'courier';
                }
            }
        }

        // System Check (internal calls)
        if (in_array('system', $allowedRoles) && ($payload['is_system'] ?? false)) {
            return 'system';
        }

        throw new InvalidOrderTransitionException(
            message: "Aktor tidak memiliki wewenang untuk mengubah status pesanan dari '{$fromStatus}' ke '{$toStatus}'.",
            fromStatus: $fromStatus,
            toStatus: $toStatus
        );
    }

    /**
     * Apply specific business logic and side effects for given transition.
     */
    protected function applyTransitionSideEffects(
        Order $order,
        string $fromStatus,
        string $toStatus,
        array $payload
    ): void {
        switch ($toStatus) {
            case 'PICKUP_ASSIGNED':
                if (isset($payload['courier_id'])) {
                    $courierId = $payload['courier_id'];
                    $order->courier_id = $courierId;

                    OrderAssignment::updateOrCreate(
                        [
                            'order_id' => $order->id,
                            'task_type' => 'pickup',
                        ],
                        [
                            'courier_id' => $courierId,
                            'status' => 'assigned',
                            'assigned_at' => now(),
                        ]
                    );
                }
                break;

            case 'PICKED_UP':
                OrderAssignment::where('order_id', $order->id)
                    ->where('task_type', 'pickup')
                    ->where('status', 'assigned')
                    ->update(['status' => 'accepted']);
                break;

            case 'AT_STORE':
                // Complete pickup assignment
                OrderAssignment::where('order_id', $order->id)
                    ->where('task_type', 'pickup')
                    ->whereIn('status', ['assigned', 'accepted'])
                    ->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);
                break;

            case 'WEIGHED':
                // Update actual weight & item quantities, calculate final_amount
                if (isset($payload['actual_weight_kg'])) {
                    $order->actual_weight_kg = (float) $payload['actual_weight_kg'];
                }

                $totalFinalAmount = 0;
                $itemsPayload = $payload['items'] ?? [];
                $itemsMap = collect($itemsPayload)->keyBy('item_id');

                foreach ($order->items as $item) {
                    $actualQty = null;
                    if ($itemsMap->has($item->id)) {
                        $actualQty = (float) $itemsMap->get($item->id)['quantity_actual'];
                    } elseif (isset($payload['actual_weight_kg']) && $item->pricing_unit_snapshot === 'kg') {
                        $actualQty = (float) $payload['actual_weight_kg'];
                    } else {
                        $actualQty = (float) $item->quantity_estimated;
                    }

                    $item->quantity_actual = $actualQty;
                    $rawLineFinal = (int) round($actualQty * $item->unit_price_snapshot);
                    $lineFinal = $rawLineFinal;

                    if ($item->minimum_charge_snapshot > 0 && $rawLineFinal < $item->minimum_charge_snapshot) {
                        $lineFinal = (int) $item->minimum_charge_snapshot;
                    }

                    $item->line_final_amount = $lineFinal;
                    $item->save();

                    $totalFinalAmount += $lineFinal;
                }

                $deliveryFee = (int) ($order->delivery_fee ?? 0);
                $order->final_amount = $totalFinalAmount + $deliveryFee;
                break;

            case 'AWAITING_PRICE_APPROVAL':
                // Handled centrally via NotificationService
                break;

            case 'PRICE_APPROVED':
                $order->price_approved_at = now();
                break;

            case 'CANCELLED':
                $order->cancelled_at = now();
                break;

            case 'PAYMENT_EXPIRED':
                $order->payment_status = 'PAYMENT_EXPIRED';
                $order->cancelled_at = now();
                Payment::where('order_id', $order->id)->where('status', 'pending')->update(['status' => 'expired']);
                break;

            case 'PAYMENT_FAILED':
                $order->payment_status = 'PAYMENT_FAILED';
                $order->cancelled_at = now();
                Payment::where('order_id', $order->id)->where('status', 'pending')->update(['status' => 'failed']);
                break;

            case 'PAID':
                $order->payment_status = 'PAID';
                break;

            case 'DELIVERY_ASSIGNED':
                if (isset($payload['courier_id'])) {
                    $courierId = $payload['courier_id'];
                    $order->courier_id = $courierId;

                    OrderAssignment::updateOrCreate(
                        [
                            'order_id' => $order->id,
                            'task_type' => 'delivery',
                        ],
                        [
                            'courier_id' => $courierId,
                            'status' => 'assigned',
                            'assigned_at' => now(),
                        ]
                    );
                }
                break;

            case 'OUT_FOR_DELIVERY':
                OrderAssignment::where('order_id', $order->id)
                    ->where('task_type', 'delivery')
                    ->where('status', 'assigned')
                    ->update(['status' => 'accepted']);
                break;

            case 'COMPLETED':
                $order->completed_at = now();
                // Complete delivery assignment
                OrderAssignment::where('order_id', $order->id)
                    ->where('task_type', 'delivery')
                    ->whereIn('status', ['assigned', 'accepted'])
                    ->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);

                // Record accounting ledger entries (US-STORE-08, FR-PAY-08..11)
                try {
                    app(LedgerService::class)->recordOrderCompletion($order);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to record ledger entries on COMPLETED: ' . $e->getMessage());
                }
                break;
        }
    }
}
