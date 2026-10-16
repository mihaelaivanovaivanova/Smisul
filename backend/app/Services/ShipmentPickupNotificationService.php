<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ShippingCarrier;
use App\Mail\ShipmentPickupNotificationMail;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Backs the "Send pickup notification" button on a Shipped Speedy order's
 * admin detail page - by request, after a recurring customer complaint that
 * Speedy's own pickup notification doesn't reliably reach them. Manual only,
 * by request: an earlier design auto-detected ShipmentStatus::ReadyForPickup
 * and sent an escalating three-stage sequence (immediate/+3d/+6d) on every
 * "Sync tracking" run, but was scrapped before ever going live in favour of
 * this simpler one-click trigger - an admin presses it whenever they judge
 * it useful, not on any automatic schedule.
 *
 * Speedy only - BOX NOW deliberately gets no equivalent, by request (its
 * own locker notification is reliable enough already). Requires a staffed
 * office/APT delivery (office_name set) - a home-address delivery has no
 * "office" for the email to name, and Speedy's courier hands it over
 * directly rather than leaving it anywhere to collect.
 */
class ShipmentPickupNotificationService
{
    /**
     * Static, like OrderReminderService::isEligibleForReminder() - no
     * injected dependencies needed, so callable straight from
     * Admin\OrderResource without resolving the whole service out of the
     * container just to check this.
     */
    public static function isEligible(Order $order): bool
    {
        $shipment = $order->shipment;

        return $order->status === OrderStatus::Shipped
            && $order->customer_email !== null
            && $shipment !== null
            && $shipment->carrier === ShippingCarrier::Speedy
            && $shipment->office_name !== null;
    }

    /**
     * @return array{sent: bool, reason: string|null}
     */
    public function send(Order $order): array
    {
        $shipment = $order->shipment;

        if (! self::isEligible($order) || $shipment === null) {
            return ['sent' => false, 'reason' => 'This order does not currently qualify for a pickup notification.'];
        }

        try {
            Mail::to($order->customer_email)->send(new ShipmentPickupNotificationMail($order, $shipment));
        } catch (Throwable $exception) {
            Log::error('Could not send the shipment pickup notification email.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return ['sent' => false, 'reason' => 'Sending failed - see the logs.'];
        }

        $shipment->update(['pickup_notification_sent_at' => now()]);

        return ['sent' => true, 'reason' => null];
    }
}
