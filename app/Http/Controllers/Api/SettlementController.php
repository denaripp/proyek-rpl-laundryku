<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SettlementPeriod;
use App\Models\Store;
use App\Services\Settlement\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SettlementController extends Controller
{
    protected SettlementService $settlementService;

    public function __construct(SettlementService $settlementService)
    {
        $this->settlementService = $settlementService;
    }

    /**
     * List settlements — scoped: platform sees all, store sees ONLY its own.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('viewAny', SettlementPeriod::class);

        $query = SettlementPeriod::with('store:id,name');

        if (!$user->hasPermissionTo('view_any_settlement')) {
            $storeIds = $user->ownedStores()->pluck('id')->merge(
                $user->storeStaff()->where('is_active', true)->pluck('store_id')
            )->unique();

            $query->whereIn('store_id', $storeIds);
        }

        $settlements = $query->latest()->paginate(15);

        return response()->json($settlements);
    }

    /**
     * Show one settlement with order breakdown.
     */
    public function show(Request $request, SettlementPeriod $settlement)
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('view', $settlement);

        return response()->json($settlement->load(['store:id,name', 'items.order:id,order_number,total_amount,completed_at']));
    }

    /**
     * Generate settlement report for a store over the eligible window.
     */
    public function store(Request $request, Store $store)
    {
        $user = $request->user();

        // Transient instance so the policy check resolves to SettlementPolicy
        $candidate = new SettlementPeriod(['store_id' => $store->id]);
        Gate::forUser($user)->authorize('create', $candidate);

        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $from = isset($validated['from']) ? \Illuminate\Support\Carbon::parse($validated['from'])->startOfDay() : null;
        $to = isset($validated['to']) ? \Illuminate\Support\Carbon::parse($validated['to'])->endOfDay() : null;

        $period = $this->settlementService->generateSettlement($store, $user, $from, $to);

        return response()->json($period->load('items'), 201);
    }

    /**
     * Verify a generated settlement (manual finance step).
     */
    public function verify(Request $request, SettlementPeriod $settlement)
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('verify', $settlement);

        $result = $this->settlementService->verifySettlement($settlement, $user);

        return response()->json($result);
    }
}