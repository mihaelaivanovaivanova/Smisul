<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Mail\OrderHandbookMail;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Backs both the automatic send (SendOrderStatusEmails, on a Delivered
 * transition) and the admin "Send kit handbook" button on the order detail
 * page - the button exists specifically because production already has
 * orders that reached Delivered before this automation existed, with no
 * handbook email ever sent for them; it's also just a normal resend path
 * going forward (a customer who says they never got it).
 *
 * Manual and repeatable, same philosophy as
 * ShipmentPickupNotificationService - handbook_email_sent_at is a record
 * of the last send, not a one-shot eligibility flag, so it never blocks a
 * second click.
 */
class OrderHandbookService
{
    /**
     * Static, like ShipmentPickupNotificationService::isEligible() - no
     * injected dependencies, so callable straight from Admin\OrderResource.
     * Relies on items() already being eager-loaded wherever this is called
     * from (see OrderService::EAGER_LOAD) - a plain relation access, not a
     * fresh query, so it never N+1s across an order list.
     */
    public static function isEligible(Order $order): bool
    {
        return $order->status === OrderStatus::Delivered
            && $order->customer_email !== null
            && $order->items->contains(fn (OrderItem $item) => $item->isBundle());
    }

    /**
     * @return array{sent: bool, reason: string|null}
     */
    public function send(Order $order): array
    {
        if (! self::isEligible($order)) {
            return ['sent' => false, 'reason' => 'This order does not currently qualify for the kit handbook email.'];
        }

        try {
            Mail::to($order->customer_email)->send(new OrderHandbookMail($order));
        } catch (Throwable $exception) {
            Log::error('Could not send the kit handbook email.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return ['sent' => false, 'reason' => 'Sending failed - see the logs.'];
        }

        $order->update(['handbook_email_sent_at' => now()]);

        return ['sent' => true, 'reason' => null];
    }
}
