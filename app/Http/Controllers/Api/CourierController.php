<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\CourierTask;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Tahap 16 — Courier dashboard.
 * Tahap 18 — Extended with earnings summary + task history endpoints.
 */
class CourierController extends Controller
{
    public function __construct(protected DashboardMetricsService $metrics) {}

    /**
     * GET /api/courier/dashboard
     * Compact dashboard summary.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermissionTo('view_courier_dashboard')) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $courier = Courier::where('user_id', $user->id)->first();
        if (!$courier) {
            return response()->json(['error' => 'Courier profile not found.'], 404);
        }

        $activeTasks = CourierTask::where('courier_id', $courier->id)
            ->whereIn('status', ['assigned', 'accepted', 'arrived', 'picked_up'])
            ->with('order')
            ->get();

        $todayCompleted = CourierTask::where('courier_id', $courier->id)
            ->whereIn('status', ['delivered', 'failed'])
            ->whereDate('completed_at', today())
            ->count();

        $lifetimeCompleted = CourierTask::where('courier_id', $courier->id)
            ->where('status', 'delivered')
            ->count();

        return response()->json([
            'courier' => $courier,
            'active_tasks' => $activeTasks,
            'stats' => [
                'today_completed' => $todayCompleted,
                'lifetime_completed' => $lifetimeCompleted,
                'is_available' => (bool) $courier->is_available,
            ],
        ]);
    }

    /**
     * PATCH /api/courier/availability
     */
    public function updateAvailability(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermissionTo('view_courier_dashboard')) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $request->validate([
            'is_available' => 'required|boolean',
        ]);

        $courier = Courier::where('user_id', $user->id)->firstOrFail();
        $courier->is_available = $request->boolean('is_available');
        $courier->save();

        // Invalidate cache so dashboard reflects new availability immediately.
        $this->metrics->recomputeScope("courier:{$courier->id}");

        return response()->json($courier);
    }

    /**
     * GET /api/courier/tasks?status=active|history
     * - status=active   => active assignments only (assigned/accepted/arrived/picked_up)
     * - status=history  => completed/failed/cancelled (paginated)
     * - omitted         => default behavior (all tasks, paginated) for backward compat.
     */
    public function tasks(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermissionTo('view_courier_dashboard')) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $courier = Courier::where('user_id', $user->id)->first();
        if (!$courier) {
            return response()->json(['tasks' => []]);
        }

        $status = $request->input('status');
        $query = CourierTask::where('courier_id', $courier->id)
            ->with('order.customer:id,name', 'order.store:id,name');

        if ($status === 'active') {
            $query->whereIn('status', ['assigned', 'accepted', 'arrived', 'picked_up']);
            return response()->json([
                'active_tasks' => $query->latest()->get(),
            ]);
        }

        if ($status === 'history') {
            // History = terminal states (delivered/failed/cancelled).
            $query->whereIn('status', ['delivered', 'failed', 'cancelled']);
            $page = max(1, (int) $request->input('page', 1));
            $perPage = min(50, max(5, (int) $request->input('per_page', 15)));
            $tasks = $query->latest('completed_at')->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'history' => $tasks->items(),
                'pagination' => [
                    'page' => $tasks->currentPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total(),
                    'last_page' => $tasks->lastPage(),
                ],
            ]);
        }

        // Backward-compatible default: all tasks, paginated.
        return response()->json($query->latest()->paginate(15));
    }

    /**
     * GET /api/courier/earnings
     * Earnings summary reused from DashboardMetricsService scope
     * "courier:<id>". Keys: earnings_today / earnings_month / earnings_lifetime.
     */
    public function earnings(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermissionTo('view_courier_dashboard')) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $courier = Courier::where('user_id', $user->id)->first();
        if (!$courier) {
            return response()->json(['error' => 'Courier profile not found.'], 404);
        }

        $scope = "courier:{$courier->id}";
        $m = fn(string $k) => $this->metrics->get($scope, $k);

        // Recent delivered tasks (last 10) for breakdown.
        $recentDelivered = CourierTask::where('courier_id', $courier->id)
            ->where('status', 'delivered')
            ->latest('completed_at')
            ->limit(10)
            ->get(['id', 'order_id', 'completed_at', 'distance_km']);

        return response()->json([
            'courier_id' => $courier->id,
            'is_available' => (bool) $courier->is_available,
            'earnings' => [
                'today' => $m('earnings_today'),
                'month' => $m('earnings_month'),
                'lifetime' => $m('earnings_lifetime'),
                'currency' => 'IDR',
            ],
            'tasks' => [
                'active' => $m('tasks_active'),
                'today_completed' => $m('tasks_today_completed'),
                'lifetime_completed' => $m('tasks_lifetime'),
                'avg_rating' => $m('avg_rating'),
            ],
            'recent_delivered' => $recentDelivered,
            'window' => [
                'today' => Carbon::today()->toDateString(),
                'month_start' => Carbon::now()->startOfMonth()->toDateString(),
            ],
        ]);
    }
}
