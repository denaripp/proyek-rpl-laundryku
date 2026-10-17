<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminMetricsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    use ApiResponse;

    /**
     * Get platform monitoring metrics with period filters.
     * FR-ADMIN-07, FR-ADMIN-08, US-ADMIN-06, US-ADMIN-07
     */
    public function metrics(Request $request): JsonResponse
    {
        $period = $request->query('period', 'all_time'); // all_time, today, this_week, this_month, this_year, custom
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $data = AdminMetricsService::getMetrics($period, $startDate, $endDate);

        return $this->successResponse(
            data: $data
        );
    }
}
