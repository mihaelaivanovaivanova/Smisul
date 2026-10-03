<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs the server's own cron calling `php artisan schedule:run` every
// minute - already documented in deployment/README-SUPERHOSTING.md, but
// this is the first real use of the scheduler, so double-check that cron
// entry actually exists on the production host before relying on this.
Schedule::command('orders:send-thirty-day-reminders')->daily();
