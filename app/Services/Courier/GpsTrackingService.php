<?php

namespace App\Services\Courier;

use App\Events\CourierLocationUpdated;
use App\Models\Courier;
use App\Models\CourierGps;
use App\Models\CourierTask;
use Illuminate\Support\Facades\Log;
use Exception;

class GpsTrackingService
{
    /**
     * Store a courier's location update.
     * Rejects updates if the courier has no active task.
     *
     * @return CourierGps|null  null when rejected (no active task)
     */
    public function storeLocation(int $courierId, float $latitude, float $longitude, array $extra = []): ?CourierGps
    {
        // Must have an active task to accept GPS updates
        $activeTask = $this->findActiveTaskForCourier($courierId);
        if (!$activeTask) {
            Log::warning('GPS update rejected: courier has no active task', [
                'courier_id' => $courierId,
            ]);
            return null;
        }

        // Update the courier's current position as well
        Courier::where('id', $courierId)->update([
            'current_latitude' => $latitude,
            'current_longitude' => $longitude,
            'last_location_update' => now(),
        ]);

        $gps = CourierGps::create([
            'task_id' => $activeTask->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy_meters' => $extra['accuracy_meters'] ?? null,
            'heading' => $extra['heading'] ?? null,
            'speed_kmh' => $extra['speed_kmh'] ?? null,
            'recorded_at' => now(),
        ]);

        // Broadcast realtime to the order's tracking channel
        try {
            broadcast(new CourierLocationUpdated(
                orderId: $activeTask->order_id,
                courierId: $courierId,
                latitude: $latitude,
                longitude: $longitude,
            ));
        } catch (Exception $e) {
            // WebSocket/Reverb failure — client falls back to polling (last known location)
            Log::warning('Broadcast failed for GPS update, client should fallback to polling', [
                'task_id' => $activeTask->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $gps;
    }

    /**
     * Get the last known location for a task.
     * Used as fallback when the newest WebSocket update hasn't arrived or failed.
     */
    public function lastKnownLocation(int $taskId): ?CourierGps
    {
        return CourierGps::where('task_id', $taskId)
            ->orderByDesc('recorded_at')
            ->first();
    }

    /**
     * Get the last known location for a courier's active task.
     */
    public function lastKnownLocationForCourier(int $courierId): ?CourierGps
    {
        $activeTask = $this->findActiveTaskForCourier($courierId);
        if (!$activeTask) {
            return null;
        }

        return $this->lastKnownLocation($activeTask->id);
    }

    /**
     * Find the courier's currently active task, or null.
     * GPS tracking only runs while the courier has an active task.
     */
    public function findActiveTaskForCourier(int $courierId): ?CourierTask
    {
        return CourierTask::where('courier_id', $courierId)
            ->whereIn('status', ['assigned', 'accepted', 'arrived', 'picked_up'])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Check whether a courier currently has an active task (GPS should be active).
     */
    public function hasActiveTask(int $courierId): bool
    {
        return $this->findActiveTaskForCourier($courierId) !== null;
    }
}