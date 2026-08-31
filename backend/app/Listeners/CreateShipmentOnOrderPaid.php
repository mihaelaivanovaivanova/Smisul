<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\Order\OrderStatusChanged;
use App\Services\ShippingService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Requests a real shipment from the order's selected carrier (BOX NOW or
 * Speedy) the moment payment is confirmed, so nobody has to remember to
 * trigger it manually — see ShippingService::createShipment()'s own
 * docblock, which was built and tested well before anything called it
 * automatically ("a future admin action is the intended caller"); this
 * listener is that automatic caller, with Admin\OrderController::
 * createShipment() kept as the manual fallback for a failed attempt or a
 * historical order.
 *
 * Deliberately swallows every failure. OrderStatusChanged is dispatched
 * synchronously from inside OrderStatusService::transitionTo()'s own
 * DB::transaction() (same as SendOrderStatusEmails), so a thrown exception
 * here would roll back the payment confirmation itself — a BOX NOW/Speedy
 * outage, a rejected address, or any other carrier-side failure must never
 * undo an already-confirmed payment. Failures are logged instead, for an
 * admin to retry manually from the order detail page.
 */
class CreateShipmentOnOrderPaid
{
    public function __construct(private readonly ShippingService $shipping) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to !== OrderStatus::Paid) {
            return;
        }

        $order = $event->order;

        try {
            $this->shipping->createShipment($order);
        } catch (Throwable $exception) {
            Log::error('Automatic shipment creation failed after payment confirmation.', [
                'order_number' => $order->order_number,
                'carrier' => $order->shipping_carrier->value,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
