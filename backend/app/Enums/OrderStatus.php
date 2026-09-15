<?php

namespace App\Enums;

/**
 * The full order lifecycle (see OrderStatusService::TRANSITIONS for which
 * moves between these are actually allowed). Every order is created as
 * Pending, then AwaitingPayment once a payment attempt exists (see
 * PaymentService::initiate()).
 *
 * Paid and Confirmed both mean "money is secured, start fulfilling" and are
 * treated identically from there on (stock is committed, the confirmation
 * email goes out, a real courier shipment is requested — see
 * OrderService::confirmPayment()/confirmCashOnDelivery(),
 * CreateShipmentOnOrderPaid, SendOrderStatusEmails). They're kept as
 * separate cases rather than one, because *how* the money is secured is
 * genuinely different and admins need to see which: Paid means a payment
 * gateway (iCard) actually captured a card payment; Confirmed means the
 * customer chose cash on delivery, and Speedy's courier will only collect
 * that money in person at hand-off — nothing has actually been paid yet
 * when an order reaches Confirmed, unlike Paid.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::AwaitingPayment => 'Awaiting Payment',
            self::Paid => 'Paid',
            self::Confirmed => 'Confirmed (cash on delivery)',
            self::Processing => 'Processing',
            self::Packed => 'Packed',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
            self::Failed => 'Failed',
        };
    }
}
