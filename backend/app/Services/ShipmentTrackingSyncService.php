<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * On demand (an admin pressing "Sync tracking" on the Orders page or the
 * dashboard - see Admin\OrderController::syncShipmentTracking()), polls
 * every Shipped order's shipment for its live status from its carrier and
 * keeps the order's own status in step with it - exactly the "future
 * sync" ShippingService::track()'s own docblock already anticipated as its
 * reason to exist. Before this, nothing in the app ever called track()
 * outside of tests: a shipment's status sat frozen at whatever it was when
 * the label was first created, and an order never left Shipped on its own.
 *
 * Scoped to orders currently sitting at Shipped or Returned - a shipment on
 * an order that hasn't reached Shipped yet (still Packed, say) has nothing
 * useful to poll for. Returned stays in scope (unlike every other terminal
 * ShipmentStatus) so a premature Returned verdict can self-correct: Speedy's
 * "Връщане в офис" ("returned to office") tracking event, confirmed live,
 * can fire mid-journey as a retry/redirect step rather than a true final
 * return, with the carrier still re-attempting delivery for days afterward
 * - see advanceOrderStatus() and OrderStatusService::TRANSITIONS' own
 * comments on Returned. Carrier-agnostic by construction - track() already
 * dispatches to whichever ShippingProviderInterface the shipment's own
 * carrier needs (see ShippingService::providerFor()), so this covers
 * Speedy and BOX NOW identically in the same pass, never just one of them.
 *
 * Kept as its own service, not inline in the controller (or
 * SyncShipmentTracking, the equivalent manual console command - see its
 * own docblock), so the sync logic stays unit-testable without either of
 * those layers - same split as OrderReminderService/SendOrderReminderEmails.
 *
 * Also sets ready_for_pickup_at the moment a shipment first reaches
 * ShipmentStatus::ReadyForPickup, and runs ShipmentPickupReminderService's
 * due sweep once at the end of every sync() call - see that service's own
 * docblock for why the pickup-reminder sequence piggybacks on this
 * on-demand button rather than running on a schedule.
 */
class ShipmentTrackingSyncService
{
    public function __construct(
        private readonly ShippingService $shipping,
        private readonly OrderStatusService $orderStatus,
        private readonly ShipmentPickupReminderService $pickupReminders,
    ) {}

    /**
     * @return array{checked: int, updated: int, orders_updated: int, failed: int, pickup_reminders_sent: int, pickup_reminders_failed: int}
     */
    public function sync(): array
    {
        // Returned is deliberately excluded from this stop-polling list -
        // see the class docblock. Delivered/Failed/Cancelled stay excluded:
        // no real-world case has shown either of those firing prematurely
        // the way Returned did, so they keep the original "stop checking
        // once terminal" behavior.
        $stopPollingStatuses = collect(ShipmentStatus::cases())
            ->filter(fn (ShipmentStatus $status) => $status->isFinal() && $status !== ShipmentStatus::Returned)
            ->map(fn (ShipmentStatus $status) => $status->value)
            ->all();

        $checked = 0;
        $updated = 0;
        $ordersUpdated = 0;
        $failed = 0;

        Shipment::query()
            ->whereNotIn('status', $stopPollingStatuses)
            ->whereNotNull('tracking_number')
            ->whereHas('order', fn ($query) => $query->whereIn('status', [OrderStatus::Shipped, OrderStatus::Returned]))
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

                    if ($shipment->status === ShipmentStatus::ReadyForPickup && $shipment->ready_for_pickup_at === null) {
                        $shipment->update(['ready_for_pickup_at' => now()]);
                    }

                    if ($this->advanceOrderStatus($shipment)) {
                        $ordersUpdated++;
                    }
                }
            });

        $pickupReminders = $this->pickupReminders->sendDueReminders();

        return [
            'checked' => $checked,
            'updated' => $updated,
            'orders_updated' => $ordersUpdated,
            'failed' => $failed,
            'pickup_reminders_sent' => $pickupReminders['sent'],
            'pickup_reminders_failed' => $pickupReminders['failed'],
        ];
    }

    /**
     * Re-checks the order is still sitting at Shipped or Returned (the
     * query above already filtered on this, but re-confirms it here in
     * case something else moved it in between) - never overrides a status
     * an admin (or any other flow) has already moved it to since.
     * Delivered/Failed/Cancelled stay terminal (the query above excludes
     * those shipments from every future sync run once recorded); Returned
     * doesn't - see the class docblock.
     *
     * A Returned order's self-correction goes back to Shipped, not
     * straight to Delivered, whenever the shipment's new status is
     * anything other than Delivered/still-Returned - the honest
     * description of "the carrier is still actively handling this parcel"
     * is Shipped, matching whatever Shipped -> Delivered/Returned would
     * already do next, rather than a special-cased direct Returned ->
     * Delivered edge that skips past that real intermediate state. If a
     * single sync run happens to observe the parcel already fully
     * delivered (no intermediate status ever recorded in between, e.g. if
     * nobody ran "Sync tracking" for a while), it still goes straight to
     * Delivered - there's no dishonesty in that case, Shipped genuinely
     * was never re-observed.
     */
    private function advanceOrderStatus(Shipment $shipment): bool
    {
        $order = $shipment->order;

        if ($order === null || ! in_array($order->status, [OrderStatus::Shipped, OrderStatus::Returned], true)) {
            return false;
        }

        $targetStatus = match (true) {
            $shipment->status === ShipmentStatus::Delivered => OrderStatus::Delivered,
            $shipment->status === ShipmentStatus::Returned => OrderStatus::Returned,
            $order->status === OrderStatus::Returned => OrderStatus::Shipped,
            default => null,
        };

        if ($targetStatus === null || $targetStatus === $order->status) {
            return false;
        }

        $this->orderStatus->transitionTo(
            $order,
            $targetStatus,
            null,
            "Updated from {$shipment->carrier->value} tracking sync: {$shipment->status->label()}",
        );

        return true;
    }
}
