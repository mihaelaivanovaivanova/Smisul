<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\Order\OrderStatusChanged;
use App\Services\ShippingService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cancels the order's shipment with the carrier the moment an admin marks
 * the order cancelled, mirroring CreateShipmentOnOrderPaid's own automatic
 * trigger. Does nothing if the order never got a shipment (never paid,
 * carrier call failed) or its shipment is already in a final state — both
 * are real, expected cases, not errors, so no log noise either way.
 *
 * Deliberately swallows every failure for the same reason as
 * CreateShipmentOnOrderPaid: OrderStatusChanged fires synchronously from
 * inside OrderStatusService::transitionTo()'s own DB::transaction(), so a
 * thrown exception here would roll back the cancellation itself - a
 * carrier-side rejection (parcel already in transit, outage, ...) must
 * never undo an admin's already-made cancellation decision. Failures are
 * logged instead, for an admin to cancel with the carrier directly.
 */
class CancelShipmentOnOrderCancelled
{
    public function __construct(private readonly ShippingService $shipping) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to !== OrderStatus::Cancelled) {
            return;
        }

        $order = $event->order;
        $shipment = $order->shipment;

        if ($shipment === null || $shipment->status->isFinal()) {
            return;
        }

        try {
            $this->shipping->cancelShipment($shipment, 'Order cancelled by store.');
        } catch (Throwable $exception) {
            Log::error('Automatic shipment cancellation failed after order was cancelled.', [
                'order_number' => $order->order_number,
                'carrier' => $order->shipping_carrier->value,
                'tracking_number' => $shipment->tracking_number,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
