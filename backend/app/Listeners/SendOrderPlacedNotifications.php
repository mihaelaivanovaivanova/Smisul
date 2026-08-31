<?php

namespace App\Listeners;

use App\Events\Order\OrderPlaced;
use App\Mail\AdminOrderNotificationMail;
use App\Mail\OrderConfirmationMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Dispatched from inside OrderService::placeOrder()'s own DB::transaction()
 * (same as CreateShipmentOnOrderPaid inside OrderStatusService::
 * transitionTo() - see that listener's docblock). A mail transport failure
 * here must never roll back an order that was already successfully
 * created and paid for - verified directly: a local mail server outage
 * previously turned a real, successful order placement into a 500 error
 * response to the customer (the order itself was still committed, since
 * OrderPlaced fires after commit, but the request appeared to fail).
 * Each notification is attempted and logged independently, so an admin
 * address rejecting mail doesn't also cost the customer their own
 * confirmation email.
 */
class SendOrderPlacedNotifications
{
    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;

        try {
            Mail::to($order->customer_email)->send(new OrderConfirmationMail($order));
        } catch (Throwable $exception) {
            Log::error('Could not send the order confirmation email.', [
                'order_number' => $order->order_number,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        $adminAddress = config('mail.admin_address');

        if ($adminAddress !== null) {
            try {
                Mail::to($adminAddress)->send(new AdminOrderNotificationMail($order));
            } catch (Throwable $exception) {
                Log::error('Could not send the admin new-order notification email.', [
                    'order_number' => $order->order_number,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }
}
