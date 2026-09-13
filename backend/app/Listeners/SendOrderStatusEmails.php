<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\Order\OrderStatusChanged;
use App\Mail\AdminOrderNotificationMail;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderDeliveredMail;
use App\Mail\OrderInvoiceMail;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Only the transitions a customer (or the store) actually cares about
 * trigger an email - e.g. Paid -> Processing (internal fulfillment
 * progress) sends nothing. Shipped is deliberately silent: the Paid email
 * already covers "we got your order", and a dedicated shipped/tracking
 * email isn't built yet. "Order created" (Pending) is deliberately silent
 * too - a card payment can still fail or be abandoned after that point, so
 * the customer- and admin-facing "order" emails wait for Paid, the first
 * point an order is actually real money. Since every live payment method is
 * card-only through iCard (see PaymentMethod::active()), every order that
 * will ever be fulfilled passes through this transition - there's no
 * checkout path that reaches Processing/Shipped/Delivered without it.
 *
 * Dispatched from inside OrderStatusService::transitionTo()'s own
 * DB::transaction() (see CreateShipmentOnOrderPaid's docblock for the same
 * point). A mail transport failure here must never roll back a status
 * transition that already happened - verified directly: a local mail
 * server outage previously made an admin's "mark as cancelled" action
 * silently fail to persist, with no indication to the admin that nothing
 * had changed. Each send is attempted and logged independently.
 */
class SendOrderStatusEmails
{
    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;

        if ($event->to === OrderStatus::Paid) {
            $this->send($order->customer_email, new OrderConfirmationMail($order), $order->order_number);

            foreach (array_unique(config('mail.order_notification_addresses', [])) as $adminAddress) {
                $this->send($adminAddress, new AdminOrderNotificationMail($order), $order->order_number);
            }

            return;
        }

        $mailable = match ($event->to) {
            OrderStatus::Cancelled => new OrderCancelledMail($order),
            OrderStatus::Delivered => new OrderDeliveredMail($order),
            default => null,
        };

        if ($mailable !== null) {
            $this->send($order->customer_email, $mailable, $order->order_number);
        }

        // Separate from OrderDeliveredMail above - this is the order's
        // legally required sales document (see OrderController::invoice()'s
        // docblock: a чл. 6, ал. 1 ЗСч first-level accounting document,
        // titled "Фактура" by Bulgarian convention even though Smisul isn't
        // VAT-registered), so it goes out for EVERY delivered order, not
        // only when the customer checked "wants_invoice" at checkout -
        // that flag only controls whether a separate billing address/
        // company/VAT number was collected (see PlaceOrderRequest), not
        // whether the sale needs documenting. It's the same document
        // either way; customer_company/customer_vat_number just render
        // blank when the customer never provided them. Sending it here (at
        // delivery, when the sale is actually realized under чл. 6, ал. 1
        // ЗСч) rather than at order placement or payment - an order that's
        // paid but not yet delivered can still be cancelled/returned, so
        // the sale isn't final until then.
        if ($event->to === OrderStatus::Delivered) {
            $this->send($order->customer_email, new OrderInvoiceMail($order), $order->order_number);
        }
    }

    private function send(string $to, Mailable $mailable, string $orderNumber): void
    {
        try {
            Mail::to($to)->send($mailable);
        } catch (Throwable $exception) {
            Log::error('Could not send an order status email.', [
                'order_number' => $orderNumber,
                'mailable' => $mailable::class,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
