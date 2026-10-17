<?php

namespace App\Console\Commands;

use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Console\Command;

class RecomputeDashboardMetrics extends Command
{
    protected $signature = 'dashboards:recompute-metrics {--scope= : specific scope to recompute (e.g. platform, store:5)}';

    protected $description = 'Recompute cached dashboard metrics (Tahap 18)';

    public function handle(DashboardMetricsService $service): int
    {
        $scope = $this->option('scope');
        if ($scope) {
            $this->info("Recomputing scope: {$scope}");
            $service->recomputeScope($scope);
        } else {
            $this->info('Recomputing all dashboard metrics...');
            $service->recomputeAll();
        }
        $this->info('Done.');
        return self::SUCCESS;
    }
}
