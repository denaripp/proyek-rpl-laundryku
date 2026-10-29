<?php

namespace App\Services\Courier;

use App\Enums\OrderStatus;
use App\Enums\CourierTaskStatus;
use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Order;
use App\Models\AuditLog;
use App\Models\OrderStatusHistory;
use App\Services\Maps\GoogleMapsService;
use App\Models\User;
use App\Notifications\NotificationTrigger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class CourierAssignmentService
{
    protected GoogleMapsService $mapsService;

    public function __construct(GoogleMapsService $mapsService)
    {
        $this->mapsService = $mapsService;
    }

    /**
     * Assign a courier to an order's task.
     * Finds the nearest eligible courier and assigns them.
     */
    public function assignCourier(Order $order, string $taskType = 'pickup'): CourierTask
    {
        // Validate order status
        if ($order->status === OrderStatus::CANCELLED->value) {
            throw new Exception('Cannot assign courier to a cancelled order.');
        }

        // Check if a task already exists
        $existing = CourierTask::where('order_id', $order->id)
            ->where('task_type', $taskType)
            ->whereIn('status', CourierTask::ACTIVE_STATUSES)
            ->first();
        if ($existing) {
            throw new Exception('An active task already exists for this order.');
        }

        // Find eligible couriers
        $couriers = $this->findEligibleCouriers($order, $taskType);

        if ($couriers->isEmpty()) {
            // No courier available — order goes to waiting state
            $this->setOrderWaitingForCourier($order, $taskType);
            throw new Exception('No eligible couriers available. Order is now waiting for courier.');
        }

        // Assign the nearest courier
        $courier = $couriers->first();
        $distance = $couriers->first()->calculated_distance ?? 0;

        return DB::transaction(function () use ($order, $courier, $taskType, $distance) {
            $task = CourierTask::create([
                'order_id' => $order->id,
                'courier_id' => $courier->id,
                'task_type' => $taskType,
                'status' => CourierTaskStatus::ASSIGNED->value,
                'assigned_at' => now(),
                'distance_km' => $distance,
            ]);

            // Update order status
            $previousStatus = $order->status;
            $statusField = $taskType === 'pickup' 
                ? OrderStatus::COURIER_ASSIGNED->value 
                : OrderStatus::COURIER_ASSIGNED_FOR_DELIVERY->value;
            $order->status = $statusField;
            $order->save();

            $actor = auth()->user() ?? ($courier->user_id ? User::find($courier->user_id) : null);
            OrderStatusHistory::record(
                order: $order,
                status: $statusField,
                previousStatus: $previousStatus,
                actor: $actor,
                reason: "Courier assigned for {$taskType}",
                metadata: [
                    'courier_id' => $courier->id,
                    'task_type' => $taskType,
                ]
            );

            // Log audit
            AuditLog::create([
                'actor_id' => $courier->user_id,
                'action' => 'courier_assigned',
                'resource_type' => 'courier_task',
                'resource_id' => $task->id,
                'new_value' => [
                    'order_id' => $order->id,
                    'courier_id' => $courier->id,
                    'task_type' => $taskType,
                    'distance_km' => $distance,
                ],
                'created_at' => now(),
            ]);

            // Tahap 15: queued side-effect notifications
            if ($courier->user_id) {
                NotificationTrigger::courierAssigned(User::find($courier->user_id), $order, $taskType);
            }
            NotificationTrigger::courierArriving($order->customer, $order);

            return $task;
        });
    }

    /**
     * Find eligible couriers sorted by distance.
     */
    public function findEligibleCouriers(Order $order, string $taskType): iterable
    {
        if ($taskType === 'delivery') {
            $destLat = $order->delivery_latitude ?? $order->pickup_latitude;
            $destLng = $order->delivery_longitude ?? $order->pickup_longitude;

            if ($destLat === null || $destLng === null) {
                $defaultAddress = $order->customer?->addresses()?->where('is_default', true)->first()
                    ?? $order->customer?->addresses()?->first();
                $destLat = $defaultAddress?->latitude;
                $destLng = $defaultAddress?->longitude;
            }

            if ($destLat === null || $destLng === null) {
                throw new Exception('Koordinat tujuan pengantaran customer belum ditentukan.');
            }

            $store = $order->store;
            if (!$store || $store->latitude === null || $store->longitude === null) {
                throw new Exception('Koordinat toko belum ditentukan.');
            }
            $originLat = $store->latitude;
            $originLng = $store->longitude;
        } else {
            $originLat = $order->pickup_latitude;
            $originLng = $order->pickup_longitude;

            if ($originLat === null || $originLng === null) {
                $defaultAddress = $order->customer?->addresses()?->where('is_default', true)->first()
                    ?? $order->customer?->addresses()?->first();
                $originLat = $defaultAddress?->latitude;
                $originLng = $defaultAddress?->longitude;
            }

            if ($originLat === null || $originLng === null) {
                throw new Exception('Koordinat penjemputan customer belum ditentukan.');
            }
        }

        $storeId = $order->store_id;
        $searchRadius = config('courier.courier_search_radius_km', 5);

        // Marketplace couriers (store_id = null) + Store couriers (belonging to this store)
        $couriers = Courier::where('is_available', true)
            ->where('is_verified', true)
            ->where(function ($q) use ($storeId) {
                $q->whereNull('store_id')
                  ->orWhere('store_id', $storeId);
            })
            ->get();

        $eligibleWithDistance = collect();
        $eligibleWithoutDistance = collect();

        foreach ($couriers as $courier) {
            // Check for conflicting active task
            $conflicting = CourierTask::where('courier_id', $courier->id)
                ->whereIn('status', ['assigned', 'accepted', 'arrived', 'picked_up'])
                ->exists();
            if ($conflicting) {
                continue;
            }

            // Couriers without current GPS location remain eligible, sorted at the end
            if ($courier->current_latitude === null || $courier->current_longitude === null) {
                $courier->calculated_distance = null;
                $eligibleWithoutDistance->push($courier);
                continue;
            }

            // Calculate distance using Google Maps or Haversine fallback
            $distance = $this->mapsService->distance(
                ['lat' => (float) $courier->current_latitude, 'lng' => (float) $courier->current_longitude],
                ['lat' => (float) $originLat, 'lng' => (float) $originLng]
            );

            if ($distance !== null && $distance <= $searchRadius) {
                $courier->calculated_distance = $distance;
                $eligibleWithDistance->push($courier);
            }
        }

        // Sort by distance ascending, then append couriers without GPS
        return $eligibleWithDistance->sortBy('calculated_distance')->concat($eligibleWithoutDistance);
    }

    /**
     * Handle courier rejection: REJECT → REASSIGNMENT.
     */
    public function handleRejection(CourierTask $task, string $reason = ''): CourierTask
    {
        return DB::transaction(function () use ($task, $reason) {
            // Mark current task as failed
            $task->status = CourierTaskStatus::FAILED->value;
            $task->failed_at = now();
            $task->failure_reason = $reason ?: 'Courier rejected the task';
            $task->save();

            // Log rejection
            Log::info('Courier rejected task', [
                'task_id' => $task->id,
                'courier_id' => $task->courier_id,
                'reason' => $reason,
            ]);

            // Increment rejection counter on courier
            $courier = $task->courier;
            $courier->total_tasks = ($courier->total_tasks ?? 0) + 1;
            $courier->save();

            $maxRejections = config('courier.max_rejections', 3);
            // Just a counter — monitoring can alert if exceeded
            $rejectionCount = CourierTask::where('courier_id', $courier->id)
                ->where('status', CourierTaskStatus::FAILED->value)
                ->where('failure_reason', 'LIKE', '%rejected%')
                ->count();
            if ($rejectionCount >= $maxRejections) {
                Log::warning('Courier exceeded max rejection threshold', [
                    'courier_id' => $courier->id,
                    'rejection_count' => $rejectionCount,
                ]);
            }

            // Attempt reassignment
            try {
                $order = $task->order;
                $newTask = $this->assignCourier($order, $task->task_type);
                return $newTask;
            } catch (Exception $e) {
                // No courier available — keep order in waiting state
                Log::info('Reassignment failed, order waiting for courier', [
                    'order_id' => $task->order_id,
                    'reason' => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }

    /**
     * Set order to waiting for courier state.
     */
    protected function setOrderWaitingForCourier(Order $order, string $taskType): void
    {
        $order->status = 'waiting_for_courier';
        $order->save();

        AuditLog::create([
            'actor_id' => null,
            'action' => 'waiting_for_courier',
            'resource_type' => 'order',
            'resource_id' => $order->id,
            'new_value' => [
                'task_type' => $taskType,
                'reason' => 'No eligible couriers available',
            ],
            'created_at' => now(),
        ]);
    }

    /**
     * Check for timed-out waiting orders.
     */
    public function expireWaitingOrders(): int
    {
        $timeout = config('courier.assignment_timeout', 30);
        $cutoff = now()->subMinutes($timeout);

        $expiredOrders = Order::where('status', 'waiting_for_courier')
            ->where('updated_at', '<', $cutoff)
            ->get();

        $count = 0;
        foreach ($expiredOrders as $order) {
            $order->status = OrderStatus::PICKUP_FAILED->value;
            $order->save();

            AuditLog::create([
                'actor_id' => null,
                'action' => 'courier_assignment_timeout',
                'resource_type' => 'order',
                'resource_id' => $order->id,
                'new_value' => [
                    'reason' => 'No courier available within timeout period',
                    'timeout_minutes' => $timeout,
                ],
                'created_at' => now(),
            ]);

            $count++;
        }

        return $count;
    }
}