<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ESI serves cached wallet data until each character's Expires header passes.
// Check every minute and sync only the characters that are due, so data lands
// within ~60s of ESI publishing it — no earlier (the cache would just re-serve).
Schedule::command('eve:sync --due')->everyMinute()->withoutOverlapping();
