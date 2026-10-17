<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    use ApiResponse;

    /**
     * List all platform transactions / payments (Read-Only).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['order.customer', 'order.store']);

        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->query('status')));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', strtoupper($request->query('payment_method')));
        }

        if ($request->filled('q')) {
            $search = '%' . $request->query('q') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'LIKE', $search)
                  ->orWhere('xendit_invoice_id', 'LIKE', $search)
                  ->orWhereHas('order', function ($oq) use ($search) {
                      $oq->where('order_number', 'LIKE', $search);
                  });
            });
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->query('start_date'), $request->query('end_date')]);
        }

        $perPage = (int) $request->query('per_page', 15);
        $payments = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $formatted = collect($payments->items())->map(fn ($p) => [
            'id' => $p->id,
            'payment_number' => $p->payment_number,
            'order_id' => $p->order_id,
            'order_number' => $p->order?->order_number,
            'store_name' => $p->order?->store?->name,
            'customer_name' => $p->order?->customer?->name,
            'payment_method' => $p->payment_method,
            'payment_type' => $p->payment_type,
            'status' => $p->status,
            'amount' => (int) $p->amount,
            'paid_at' => $p->paid_at?->toIso8601String(),
            'expired_at' => $p->expired_at?->toIso8601String(),
            'xendit_invoice_url' => $p->xendit_invoice_url,
            'created_at' => $p->created_at?->toIso8601String(),
        ]);

        return $this->successResponse(
            data: $formatted,
            meta: [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ]
        );
    }

    /**
     * Show detailed payment record.
     */
    public function show(string $id): JsonResponse
    {
        $payment = Payment::with(['order.customer', 'order.store', 'order.items'])->find($id);

        if (!$payment) {
            return $this->errorResponse('NOT_FOUND', 'Transaksi pembayaran tidak ditemukan.', null, 404);
        }

        return $this->successResponse(
            data: $payment
        );
    }
}
