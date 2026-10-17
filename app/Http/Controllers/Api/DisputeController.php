<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Order;
use App\Services\Dispute\DisputeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DisputeController extends Controller
{
    protected DisputeService $disputeService;

    public function __construct(DisputeService $disputeService)
    {
        $this->disputeService = $disputeService;
    }

    /**
     * List disputes scoped by role.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('viewAny', Dispute::class);

        $query = Dispute::with(['order', 'openedBy', 'assignedTo']);

        if (!$user->hasPermissionTo('view_any_dispute')) {
            $storeIds = $user->ownedStores()->pluck('id')->merge(
                $user->storeStaff()->where('is_active', true)->pluck('store_id')
            )->unique();

            $query->where(function ($q) use ($user, $storeIds) {
                $q->where('opened_by', $user->id)
                  ->orWhereHas('order', function ($oq) use ($user, $storeIds) {
                      $oq->where('customer_id', $user->id);
                      if ($storeIds->isNotEmpty()) {
                          $oq->orWhereIn('store_id', $storeIds);
                      }
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json($query->latest()->paginate(15));
    }

    /**
     * Show a single dispute with its timeline events.
     */
    public function show(Request $request, Dispute $dispute)
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('view', $dispute);

        return response()->json($dispute->load(['order', 'openedBy', 'assignedTo', 'resolvedBy', 'refund', 'events']));
    }

    /**
     * Open a dispute (Complaint stage).
     */
    public function store(Request $request, Order $order)
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('create', [Dispute::class, $order]);

        $validated = $request->validate([
            'reason' => 'required|string|in:' . implode(',', DisputeService::REASONS),
            'description' => 'required|string|max:5000',
            'evidence' => 'nullable|array',
        ]);

        $dispute = $this->disputeService->openDispute(
            $order,
            $user,
            $validated['reason'],
            $validated['description'],
            $validated['evidence'] ?? null
        );

        return response()->json($dispute, 201);
    }

    /**
     * Dispute Review stage (authorized reviewer only).
     */
    public function review(Request $request, Dispute $dispute)
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('review', $dispute);

        $validated = $request->validate([
            'note' => 'nullable|string|max:2000',
        ]);

        $result = $this->disputeService->startReview($dispute, $user, $validated['note'] ?? null);

        return response()->json($result);
    }

    /**
     * Request additional information from the complainant.
     */
    public function requestInfo(Request $request, Dispute $dispute)
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('review', $dispute);

        $validated = $request->validate([
            'question' => 'required|string|max:2000',
        ]);

        $result = $this->disputeService->requestMoreInfo($dispute, $user, $validated['question']);

        return response()->json($result);
    }

    /**
     * Investigation stage (authorized reviewer only).
     */
    public function investigate(Request $request, Dispute $dispute)
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('review', $dispute);

        $validated = $request->validate([
            'note' => 'nullable|string|max:2000',
        ]);

        $result = $this->disputeService->startInvestigation($dispute, $user, $validated['note'] ?? null);

        return response()->json($result);
    }

    /**
     * Resolution stage — HUMAN DECISION ONLY (Platform Admin/Owner with permission).
     * Refund outcomes create a REQUESTED refund via RefundService (Tahap 10);
     * nothing is auto-executed.
     */
    public function resolve(Request $request, Dispute $dispute)
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('resolve', $dispute);

        $validated = $request->validate([
            'outcome' => 'required|string|in:' . implode(',', DisputeService::OUTCOMES),
            'resolution_note' => 'required|string|max:5000',
            'refund_amount' => 'nullable|numeric|min:0.01',
            'refund_method' => 'nullable|string|in:bank_transfer,wallet,cod,manual',
        ]);

        $result = $this->disputeService->resolve(
            $dispute,
            $user,
            $validated['outcome'],
            $validated['resolution_note'],
            isset($validated['refund_amount']) ? (float) $validated['refund_amount'] : null,
            $validated['refund_method'] ?? null
        );

        return response()->json($result->load('refund'));
    }

    /**
     * Reject the dispute (no compensation).
     */
    public function reject(Request $request, Dispute $dispute)
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('resolve', $dispute);

        $validated = $request->validate([
            'note' => 'required|string|max:5000',
        ]);

        $result = $this->disputeService->reject($dispute, $user, $validated['note']);

        return response()->json($result);
    }
}