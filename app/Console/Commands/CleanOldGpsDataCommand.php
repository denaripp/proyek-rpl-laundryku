<?php

namespace App\Console\Commands;

use App\Models\CourierGps;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanOldGpsDataCommand extends Command
{
    protected $signature = 'courier:clean-old-gps-data';
    protected $description = 'Hapus data courier_gps yang lebih lama dari retention period';

    public function handle()
    {
        $retentionDays = config('courier.gps_retention_period', 30);

        $deleted = CourierGps::where('recorded_at', '<', now()->subDays($retentionDays))
            ->delete();

        $this->info("Cleaned {$deleted} old courier GPS data records older than {$retentionDays} days.");

        return 0;
    }
}