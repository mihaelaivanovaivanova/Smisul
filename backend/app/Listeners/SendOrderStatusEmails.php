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

        // Separate from OrderDeliveredMail above — this is the order's
        // legally required sales document (see OrderController::invoice()'s
        // docblock: a чл. 6, ал. 1 ЗСч first-level accounting document,
        // titled "Фактура" by Bulgarian convention even though Smisul isn't
        // VAT-registered), so it goes out for EVERY delivered order, not
        // only when the customer checked "wants_invoice" at checkout —
        // that flag only controls whether a separate billing address/
        // company/VAT number was collected (see PlaceOrderRequest), not
        // whether the sale needs documenting. It's the same document
        // either way; customer_company/customer_vat_number just render
        // blank when the customer never provided them. Sending it here (at
        // delivery, when the sale is actually realized) rather than at
        // order placement matters most for BOX NOW наложен платеж orders,
        // where no payment has even occurred yet at placement time.
        if ($event->to === OrderStatus::Delivered) {
            Mail::to($order->customer_email)->send(new OrderInvoiceMail($order));
        }
    }
}
