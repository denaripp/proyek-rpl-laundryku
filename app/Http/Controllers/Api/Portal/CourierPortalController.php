<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\CourierTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourierPortalController extends Controller
{
    /**
     * Get tasks for authenticated courier.
     */
    public function tasks(Request $request): JsonResponse
    {
        $user = $request->user();
        $courier = Courier::where('user_id', $user->id)->first();

        if (! $courier) {
            return response()->json([
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
}
