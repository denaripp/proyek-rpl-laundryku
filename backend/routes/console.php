<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler: Otomatis membatalkan pesanan yang belum dibayar setelah melewati batas waktu
Schedule::command('orders:expire-unpaid')->everyMinute()->withoutOverlapping();
