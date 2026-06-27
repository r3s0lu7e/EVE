<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ESI caches wallet data for 1 hour and republishes on round clock boundaries.
// Check every minute and sync only characters past their randomized next-sync
// time (Expires + jitter), so data lands shortly after ESI publishes it while
// staying off the shared cache boundary that every other client polls.
Schedule::command('eve:sync --due')->everyMinute()->withoutOverlapping();
