<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('payments:expire')->everyFiveMinutes()->withoutOverlapping()->runInBackground();

Schedule::command('courier:clean-old-gps-data')->daily()->at('02:00')->withoutOverlapping();

// Tahap 18: recompute dashboard metrics every 10 minutes
Schedule::command('dashboards:recompute-metrics')->everyTenMinutes()->withoutOverlapping()->runInBackground();