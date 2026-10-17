<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    use ApiResponse;

    /**
     * List all platform orders cross-store (Read-Only).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['customer', 'store', 'courier', 'items']);

        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->query('status')));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', strtoupper($request->query('payment_status')));
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->query('store_id'));
        }

        if ($request->filled('q')) {
            $search = '%' . $request->query('q') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'LIKE', $search)
                  ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'LIKE', $search)->orWhere('phone', 'LIKE', $search))
                  ->orWhereHas('store', fn ($sq) => $sq->where('name', 'LIKE', $search));
            });
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->query('start_date'), $request->query('end_date')]);
        }

        $perPage = (int) $request->query('per_page', 15);
        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $formatted = collect($orders->items())->map(fn ($o) => [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'status' => $o->status,
            'payment_status' => $o->payment_status,
            'customer' => $o->customer ? [
                'id' => $o->customer->id,
                'name' => $o->customer->name,
                'phone' => $o->customer->phone,
            ] : null,
            'store' => $o->store ? [
                'id' => $o->store->id,
                'name' => $o->store->name,
            ] : null,
            'courier' => $o->courier ? [
                'id' => $o->courier->id,
                'name' => $o->courier->name,
            ] : null,
            'estimated_amount' => (int) $o->estimated_amount,
            'final_amount' => $o->final_amount !== null ? (int) $o->final_amount : null,
            'commission_amount' => (int) $o->commission_amount,
            'estimated_weight_kg' => (float) $o->estimated_weight_kg,
            'actual_weight_kg' => $o->actual_weight_kg !== null ? (float) $o->actual_weight_kg : null,
            'created_at' => $o->created_at?->toIso8601String(),
            'completed_at' => $o->completed_at?->toIso8601String(),
        ]);

        return $this->successResponse(
            data: $formatted,
            meta: [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        );
    }

    /**
     * Show detailed order snapshot.
     */
    public function show(string $id): JsonResponse
    {
        $order = Order::with(['customer', 'store', 'courier', 'items', 'payments', 'complaints'])->find($id);

        if (!$order) {
            return $this->errorResponse('NOT_FOUND', 'Pesanan tidak ditemukan.', null, 404);
        }

        return $this->successResponse(
            data: $order
        );
    }
}
