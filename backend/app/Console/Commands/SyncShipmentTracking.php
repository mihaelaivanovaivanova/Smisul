<?php

namespace App\Console\Commands;

use App\Services\ShipmentTrackingSyncService;
use Illuminate\Console\Command;

/**
 * Manual CLI equivalent of the admin "Sync tracking" button (see
 * Admin\OrderController::syncShipmentTracking()) - not scheduled, by
 * request (see routes/console.php). Thin wrapper around
 * ShipmentTrackingSyncService, which owns the actual sync/advance logic so
 * it stays unit-testable without the console layer.
 */
class SyncShipmentTracking extends Command
{
    protected $signature = 'shipments:sync-tracking';

    protected $description = 'Poll every Shipped order\'s shipment for its live carrier status, and advance it to Delivered/Returned to match';

    public function handle(ShipmentTrackingSyncService $service): int
    {
        $result = $service->sync();

        $this->info("Checked {$result['checked']} shipment(s), {$result['updated']} status change(s), {$result['orders_updated']} order(s) advanced.");

        if ($result['failed'] > 0) {
            $this->warn("{$result['failed']} failed to sync - see the log for details.");
        }

        return self::SUCCESS;
    }
}
