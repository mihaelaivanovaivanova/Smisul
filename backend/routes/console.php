<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nothing is scheduled here, by request - both orders:send-thirty-day-
// reminders and shipments:sync-tracking only ever run on demand now, via
// their own "Send reminder emails"/"Sync tracking" buttons on the admin
// Orders page/dashboard (see Admin\OrderController), or manually through
// these commands themselves. See OrderReminderService's and
// ShipmentTrackingSyncService's own docblocks.
