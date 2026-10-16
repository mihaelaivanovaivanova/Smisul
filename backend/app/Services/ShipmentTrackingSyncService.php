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
 * useful to poll for. Returned and Failed both stay in scope (unlike
 * Delivered/Cancelled) so a shipment stuck at either can still self-correct
 * once a later sync genuinely confirms Delivered or Returned - see
 * advanceOrderStatus() and ShipmentStatus::isSticky()'s own docblock.
 * Confirmed live: a real shipment's history had a failed delivery attempt
 * ("Неуспешна доставка") chronologically *before* its real Returned event,
 * and briefly got stuck recording Failed during the same broken-deploy
 * window that caused the Returned issue - excluding Failed from polling too
 * would have left it stuck forever, the exact failure mode that window
 * already taught us to avoid. Carrier-agnostic by construction - track()
 * already dispatches to whichever ShippingProviderInterface the shipment's
 * own carrier needs (see ShippingService::providerFor()), so this covers
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
        // Returned and Failed are deliberately excluded from this
        // stop-polling list - see the class docblock. Delivered/Cancelled
        // stay excluded: no real-world case has shown either of those
        // firing prematurely, so they keep the original "stop checking
        // once terminal" behavior.
        $stopPollingStatuses = collect(ShipmentStatus::cases())
            ->filter(fn (ShipmentStatus $status) => $status->isFinal()
                && $status !== ShipmentStatus::Returned
                && $status !== ShipmentStatus::Failed)
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

                    if ($shipment->status !== $previousStatus) {
                        $updated++;

                        if ($shipment->status === ShipmentStatus::ReadyForPickup && $shipment->ready_for_pickup_at === null) {
                            $shipment->update(['ready_for_pickup_at' => now()]);
                        }
                    }

                    // Always attempted, not just when the shipment's own
                    // status changed this run - a shipment can already be
                    // sitting at the right status (e.g. corrected by hand,
                    // or by a direct track() call outside this loop) while
                    // its order hasn't caught up yet, and advanceOrderStatus()
                    // already no-ops cheaply via its own guards when there's
                    // genuinely nothing to do (see its own docblock).
                    // Confirmed live: without this, a shipment whose status
                    // was fixed by a one-off direct track() call stayed
                    // desynced from its order on every later sync run, since
                    // "no change this run" always skipped the order check.
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
     * Delivered/Cancelled stay terminal (the query above excludes those
     * shipments from every future sync run once recorded); Returned/Failed
     * don't - see the class docblock. A shipment sitting at Failed has no
     * order-level target of its own ($targetStatus falls through to null
     * below, same as any other in-progress status) - it's just kept
     * eligible for polling so a later real Delivered/Returned can still be
     * caught, not treated as an order-level outcome by itself. The only
     * move out of Returned here is to Delivered, on a genuine later
     * confirmation - see ShipmentStatus::isSticky()'s own docblock for why
     * a broader "Returned -> Shipped whenever anything else shows up" rule
     * was tried and reverted.
     */
    private function advanceOrderStatus(Shipment $shipment): bool
    {
        $order = $shipment->order;

        if ($order === null || ! in_array($order->status, [OrderStatus::Shipped, OrderStatus::Returned], true)) {
            return false;
        }

        $targetStatus = match ($shipment->status) {
            ShipmentStatus::Delivered => OrderStatus::Delivered,
            ShipmentStatus::Returned => OrderStatus::Returned,
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
