<?php

namespace App\Console\Commands;

use App\Services\ShipmentTrackingSyncService;
use Illuminate\Console\Command;

/**
 * Scheduled periodically (see routes/console.php) - thin wrapper around
 * ShipmentTrackingSyncService, which owns the actual sync/advance logic so
 * it stays unit-testable without the console layer.
 */
class SyncShipmentTracking extends Command
{
    protected $signature = 'shipments:sync-tracking';

    protected $description = 'Poll every non-final shipment\'s live status from its carrier, and advance a Shipped order to Delivered/Returned to match';

    public function handle(ShipmentTrackingSyncService $service): int
    {
        $result = $service->syncDue();

        $this->info("Checked {$result['checked']} shipment(s), {$result['updated']} status change(s), {$result['orders_updated']} order(s) advanced.");

        if ($result['failed'] > 0) {
            $this->warn("{$result['failed']} failed to sync - see the log for details.");
        }

        return self::SUCCESS;
    }
}
