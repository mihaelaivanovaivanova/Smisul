<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Mail\ShipmentReadyForPickupMail;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

/**
 * Sends the escalating "your parcel is waiting for pickup" email sequence
 * (immediate / +3 days / +6 days, still unpicked) for a shipment sitting at
 * ShipmentStatus::ReadyForPickup - by request, after a recurring customer
 * complaint that Speedy's own pickup notification doesn't reliably reach
 * them. Not scheduled (this app deliberately has no cron jobs, see
 * routes/console.php, by earlier request) - sendDueReminders() is instead
 * called once at the end of every ShipmentTrackingSyncService::sync() run,
 * so these go out whenever "Sync tracking" is next clicked rather than on a
 * guaranteed daily cadence. See ShipmentTrackingSyncService for where
 * ready_for_pickup_at itself gets set, the moment a shipment first reaches
 * ReadyForPickup.
 */
class ShipmentPickupReminderService
{
    private const SECOND_REMINDER_AFTER_DAYS = 3;

    private const THIRD_REMINDER_AFTER_DAYS = 6;

    /**
     * Gated behind config('services.shipping.pickup_reminders_enabled')
     * (SHIPMENT_PICKUP_REMINDERS_ENABLED) - deliberately false in
     * production right now, by request, until Speedy's real "ready for
     * pickup" code (and whether a usable delivery-estimate field exists)
     * is confirmed from the diagnostic logging already in place (see
     * SpeedyShippingProvider::track()). ShipmentTrackingSyncService keeps
     * setting ready_for_pickup_at and advancing order/shipment statuses
     * normally either way - only the reminder emails themselves pause, so
     * nothing needs to be "caught up" once this is flipped on; it'll just
     * start sending for whatever's eligible at that point.
     *
     * @return array{sent: int, failed: int}
     */
    public function sendDueReminders(): array
    {
        if (! config('services.shipping.pickup_reminders_enabled')) {
            return ['sent' => 0, 'failed' => 0];
        }

        $sent = 0;
        $failed = 0;

        foreach ($this->dueShipments()->cursor() as $shipment) {
            $stage = $this->nextDueStage($shipment);

            if ($stage === null) {
                continue;
            }

            if ($this->sendReminder($shipment, $stage)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * @return Builder<Shipment>
     */
    private function dueShipments(): Builder
    {
        return Shipment::query()
            ->where('status', ShipmentStatus::ReadyForPickup)
            ->whereNotNull('ready_for_pickup_at')
            ->where(function (Builder $query) {
                $query->whereNull('pickup_reminder_sent_at')
                    ->orWhereNull('pickup_reminder_2_sent_at')
                    ->orWhereNull('pickup_reminder_3_sent_at');
            })
            ->with('order');
    }

    /**
     * Which of the three reminders (if any) is due right now for this
     * shipment - at most one per call, oldest-unsent-stage first, so a
     * shipment that's gone 7+ days unpicked without sync running in
     * between still gets stage 1, then (on the next run) stage 2 and 3 in
     * order, rather than jumping straight to the final one.
     */
    private function nextDueStage(Shipment $shipment): ?int
    {
        if ($shipment->pickup_reminder_sent_at === null) {
            return 1;
        }

        if ($shipment->pickup_reminder_2_sent_at === null
            && $shipment->ready_for_pickup_at->lte(now()->subDays(self::SECOND_REMINDER_AFTER_DAYS))) {
            return 2;
        }

        if ($shipment->pickup_reminder_3_sent_at === null
            && $shipment->ready_for_pickup_at->lte(now()->subDays(self::THIRD_REMINDER_AFTER_DAYS))) {
            return 3;
        }

        return null;
    }

    /**
     * Mirrors OrderReminderService::sendReminder()'s own resilience
     * convention: a mail transport failure is logged and skipped, never
     * thrown, and never marks the stage sent - it's retried on the next
     * sync run instead of silently never reminded.
     */
    private function sendReminder(Shipment $shipment, int $stage): bool
    {
        $order = $shipment->order;

        if ($order === null || $order->customer_email === null) {
            return false;
        }

        try {
            Mail::to($order->customer_email)->send(new ShipmentReadyForPickupMail($order, $shipment, $stage));
        } catch (Throwable $exception) {
            Log::error('Could not send the pickup reminder email.', [
                'shipment_id' => $shipment->id,
                'stage' => $stage,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        $column = match ($stage) {
            1 => 'pickup_reminder_sent_at',
            2 => 'pickup_reminder_2_sent_at',
            3 => 'pickup_reminder_3_sent_at',
            // Unreachable - sendReminder() is only ever called internally
            // with a stage nextDueStage() just returned (1, 2, or 3) -
            // kept so this match stays exhaustive for PHPStan.
            default => throw new InvalidArgumentException("Invalid pickup reminder stage [{$stage}]."),
        };

        $shipment->update([$column => now()]);

        return true;
    }
}
