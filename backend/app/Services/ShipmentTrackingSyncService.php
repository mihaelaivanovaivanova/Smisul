<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Periodically polls every non-final shipment's live status from its
 * carrier and keeps the parent order's own status in step with it -
 * exactly the "future scheduled sync" ShippingService::track()'s own
 * docblock already anticipated as its reason to exist. Before this,
 * nothing in the app ever called track() outside of tests: a shipment's
 * status sat frozen at whatever it was when the label was first created,
 * and an order never left Shipped on its own.
 *
 * Carrier-agnostic by construction - track() already dispatches to
 * whichever ShippingProviderInterface the shipment's own carrier needs
 * (see ShippingService::providerFor()), so this covers Speedy and BOX NOW
 * identically, not just one of them.
 *
 * Scheduled via SyncShipmentTracking (see routes/console.php); kept as its
 * own service, not inline in the command, so the sync logic stays
 * unit-testable without the console layer - same split as
 * OrderReminderService/SendOrderReminderEmails.
 */
class ShipmentTrackingSyncService
{
    public function __construct(
        private readonly ShippingService $shipping,
        private readonly OrderStatusService $orderStatus,
    ) {}

    /**
     * @return array{checked: int, updated: int, orders_updated: int, failed: int}
     */
    public function syncDue(): array
    {
        $finalStatuses = collect(ShipmentStatus::cases())
            ->filter(fn (ShipmentStatus $status) => $status->isFinal())
            ->map(fn (ShipmentStatus $status) => $status->value)
            ->all();

        $checked = 0;
        $updated = 0;
        $ordersUpdated = 0;
        $failed = 0;

        Shipment::query()
            ->whereNotIn('status', $finalStatuses)
            ->whereNotNull('tracking_number')
            ->with('order')
            ->chunkById(50, function ($shipments) use (&$checked, &$updated, &$ordersUpdated, &$failed) {
                foreach ($shipments as $shipment) {
                    $checked++;
                    $previousStatus = $shipment->status;

                    try {
                        $this->shipping->track($shipment);
                    } catch (Throwable $exception) {
                        $failed++;
                        Log::warning('Shipment tracking sync failed.', [
                            'shipment_id' => $shipment->id,
                            'carrier' => $shipment->carrier->value,
                            'tracking_number' => $shipment->tracking_number,
                            'exception' => $exception->getMessage(),
                        ]);

                        continue;
                    }

                    $shipment->refresh();

                    if ($shipment->status === $previousStatus) {
                        continue;
                    }

                    $updated++;

                    if ($this->advanceOrderStatus($shipment)) {
                        $ordersUpdated++;
                    }
                }
            });

        return ['checked' => $checked, 'updated' => $updated, 'orders_updated' => $ordersUpdated, 'failed' => $failed];
    }

    /**
     * Only ever advances an order sitting at exactly Shipped - never
     * overrides a status an admin (or any other flow) has already moved it
     * to since, and never fires twice for the same shipment: Delivered and
     * Returned are both terminal ShipmentStatus values, so the query above
     * excludes this shipment from every future sync run the moment either
     * is recorded.
     */
    private function advanceOrderStatus(Shipment $shipment): bool
    {
        $order = $shipment->order;

        if ($order === null || $order->status !== OrderStatus::Shipped) {
            return false;
        }

        $targetStatus = match ($shipment->status) {
            ShipmentStatus::Delivered => OrderStatus::Delivered,
            ShipmentStatus::Returned => OrderStatus::Returned,
            default => null,
        };

        if ($targetStatus === null) {
            return false;
        }

        $this->orderStatus->transitionTo(
            $order,
            $targetStatus,
            null,
            "Auto-updated from {$shipment->carrier->value} tracking: {$shipment->status->label()}",
        );

        return true;
    }
}
