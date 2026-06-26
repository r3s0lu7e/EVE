<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ESI caches wallet transactions ~1h, so hourly sync is the useful cadence.
Schedule::command('eve:sync')->hourly()->withoutOverlapping();
