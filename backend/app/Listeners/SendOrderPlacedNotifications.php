<?php

namespace App\Listeners;

use App\Events\Order\OrderPlaced;
use App\Mail\AdminOrderNotificationMail;
use App\Mail\OrderConfirmationMail;
use Illuminate\Support\Facades\Mail;

class SendOrderPlacedNotifications
{
    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;

        Mail::to($order->customer_email)->send(new OrderConfirmationMail($order));

        $adminAddress = config('mail.admin_address');

        if ($adminAddress !== null) {
            Mail::to($adminAddress)->send(new AdminOrderNotificationMail($order));
        }
    }
}
