<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\Order\OrderStatusChanged;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderConfirmedMail;
use App\Mail\OrderDeliveredMail;
use App\Mail\OrderShippedMail;
use Illuminate\Support\Facades\Mail;

/**
 * Only the transitions a customer actually cares about trigger an email —
 * e.g. Paid -> Processing (internal fulfillment progress) sends nothing.
 * "Order created" isn't handled here at all; that's OrderPlaced's own
 * listener (SendOrderPlacedNotifications), fired at placement rather than
 * on a status change.
 */
class SendOrderStatusEmails
{
    public function handle(OrderStatusChanged $event): void
    {
        $mailable = match ($event->to) {
            OrderStatus::Paid => new OrderConfirmedMail($event->order),
            OrderStatus::Cancelled => new OrderCancelledMail($event->order),
            OrderStatus::Shipped => new OrderShippedMail($event->order),
            OrderStatus::Delivered => new OrderDeliveredMail($event->order),
            default => null,
        };

        if ($mailable !== null) {
            Mail::to($event->order->customer_email)->send($mailable);
        }
    }
}
