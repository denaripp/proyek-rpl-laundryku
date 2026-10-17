<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourierTask;
use App\Services\Courier\CourierAssignmentService;
use App\Services\Courier\GpsTrackingService;
use App\Notifications\NotificationTrigger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CourierTaskController extends Controller
{
    protected CourierAssignmentService $assignmentService;
    protected GpsTrackingService $gpsService;

    public function __construct(CourierAssignmentService $assignmentService, GpsTrackingService $gpsService)
    {
        $this->assignmentService = $assignmentService;
        $this->gpsService = $gpsService;
    }

    /**
     * Accept a task: ASSIGNED → ACCEPTED.
     */
    public function accept(Request $request, CourierTask $task)
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('update', $task->courier);

        if ($task->status !== 'assigned') {
            return response()->json(['error' => 'Task can only be accepted when in ASSIGNED status.'], 422);
        }

        $task->status = 'accepted';
        $task->accepted_at = now();
        $task->save();

        return response()->json($task);
    }

    /**
     * Reject a task: ASSIGNED → REJECT → REASSIGNMENT via CourierAssignmentService.
     */
    public function reject(Request $request, CourierTask $task)
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('update', $task->courier);

        if ($task->status !== 'assigned') {
            return response()->json(['error' => 'Task can only be rejected when in ASSIGNED status.'], 422);
        }

        $reason = $request->input('reason', 'Courier rejected the task');

        try {
            $newTask = $this->assignmentService->handleRejection($task, $reason);
            return response()->json([
                'message' => 'Task rejected and reassigned.',
                'previous_task' => $task->fresh(),
                'new_task' => $newTask,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Task rejected but no courier available for reassignment. Order is waiting for courier.',
                'previous_task' => $task->fresh(),
            ], 200);
        }
    }

    /**
     * Update task status: arrived / picked_up / delivered / failed.
     */
    public function updateStatus(Request $request, CourierTask $task)
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('update', $task->courier);

        $validated = $request->validate([
            'status' => 'required|string|in:arrived,picked_up,delivered,failed',
            'failure_reason' => 'nullable|string|max:500',
        ]);

        $newStatus = $validated['status'];
        $allowedTransitions = [
            'accepted' => ['arrived'],
            'arrived' => ['picked_up', 'failed'],
            'picked_up' => ['delivered', 'failed'],
        ];

        $currentStatus = $task->status;
        $allowed = $allowedTransitions[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowed)) {
            return response()->json([
                'error' => "Cannot transition from {$currentStatus} to {$newStatus}.",
            ], 422);
        }

        $task->status = $newStatus;

        if ($newStatus === 'arrived') {
            $task->arrived_at = now();

            // Tahap 15: queued side-effect notification (courier arriving at pickup)
            NotificationTrigger::courierArriving($task->order->customer, $task->order);
        } elseif ($newStatus === 'picked_up') {
            $task->completed_at = now();
            $task->order->status = 'picked_up';
            $task->order->save();

            // Tahap 15: queued side-effect notification
            NotificationTrigger::pickupCompleted($task->order->customer, $task->order);
        } elseif ($newStatus === 'delivered') {
            $task->completed_at = now();
            $task->order->status = 'delivered';
            $task->order->save();

            // Tahap 15: queued side-effect notification
            NotificationTrigger::completed($task->order->customer, $task->order);
        } elseif ($newStatus === 'failed') {
            $task->failed_at = now();
            $task->failure_reason = $validated['failure_reason'] ?? 'No reason provided';
        }

        $task->save();

        return response()->json($task);
    }

    /**
     * Update GPS location for the courier's active task.
     */
    public function updateLocation(Request $request, CourierTask $task)
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('update', $task->courier);

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy_meters' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|between:0,360',
            'speed_kmh' => 'nullable|numeric|min:0',
        ]);

        $gps = $this->gpsService->storeLocation(
            $task->courier_id,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            $validated
        );

        if (!$gps) {
            return response()->json(['error' => 'GPS update rejected: no active task for this courier.'], 422);
        }

        return response()->json($gps);
    }
}