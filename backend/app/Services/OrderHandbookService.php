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
 * Backs the admin "Send kit handbook" button on the order detail page -
 * for the backlog of orders that reached Delivered before this feature
 * existed (handbook content is now included directly in OrderDeliveredMail
 * for every order going forward, see that Mailable's own docblock - this
 * service/mailable pair is no longer part of the automatic send). Also
 * just a normal resend path afterward (a customer who says they never got
 * it, or never will since their order predates the merge).
 *
 * Manual and repeatable, same philosophy as
 * ShipmentPickupNotificationService - handbook_email_sent_at is a record
 * of the last send, not a one-shot eligibility flag, so it never blocks a
 * second click.
 */
class OrderHandbookService
{
    /**
     * True when at least one line on the order is a kit (bundle_id set) -
     * shared with OrderDeliveredMail's own decision to attach the PDF
     * there instead, so the two never check this differently. Relies on
     * items() already being eager-loaded wherever this is called from (see
     * OrderService::EAGER_LOAD) - a plain relation access, not a fresh
     * query, so it never N+1s across an order list.
     */
    public static function orderHasKit(Order $order): bool
    {
        return $order->items->contains(fn (OrderItem $item) => $item->isBundle());
    }

    /**
     * Static, like ShipmentPickupNotificationService::isEligible() - no
     * injected dependencies, so callable straight from Admin\OrderResource.
     */
    public static function isEligible(Order $order): bool
    {
        return $order->status === OrderStatus::Delivered
            && $order->customer_email !== null
            && self::orderHasKit($order);
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
