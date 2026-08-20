<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\Order\OrderStatusChanged;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderDeliveredMail;
use App\Mail\OrderInvoiceMail;
use Illuminate\Support\Facades\Mail;

/**
 * Only the transitions a customer actually cares about trigger an email —
 * e.g. Paid -> Processing (internal fulfillment progress) sends nothing.
 * Paid and Shipped are deliberately silent too: the placement confirmation
 * already covers "we got your order" and a tracking link, so a second
 * "confirmed"/"shipped" email was judged redundant noise. "Order created"
 * isn't handled here at all; that's OrderPlaced's own listener
 * (SendOrderPlacedNotifications), fired at placement rather than on a
 * status change.
 */
class SendOrderStatusEmails
{
    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;

        $mailable = match ($event->to) {
            OrderStatus::Cancelled => new OrderCancelledMail($order),
            OrderStatus::Delivered => new OrderDeliveredMail($order),
            default => null,
        };

        if ($mailable !== null) {
            Mail::to($order->customer_email)->send($mailable);
        }

        // Separate from OrderDeliveredMail above — an invoice is a
        // financial document, not a "your parcel arrived" notice, and only
        // goes out at all if the customer actually asked for one at
        // checkout (see PlaceOrderRequest's wants_invoice field).
        if ($event->to === OrderStatus::Delivered && $order->wants_invoice) {
            Mail::to($order->customer_email)->send(new OrderInvoiceMail($order));
        }
    }
}
