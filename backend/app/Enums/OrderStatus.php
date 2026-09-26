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
    // Retired: it had no automated behavior of its own (no email, no
    // shipment request - see SendOrderStatusEmails' own docblock) and was
    // just an extra manual click between Paid/Confirmed and Packed with no
    // real distinction the store actually used. The case stays here on
    // purpose (same reasoning as ShippingCarrier::Econt): existing
    // order_status_histories rows recorded real orders passing through it,
    // and Eloquent's native enum cast throws - not silently nulls - when a
    // persisted value has no matching case, so removing it outright would
    // 500 every read of that history. See OrderStatusService::TRANSITIONS
    // for where it was actually cut out: Paid/Confirmed now go straight to
    // Packed, and nothing can transition into Processing anymore.
    case Processing = 'processing';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    // Retired: it had no automated behavior of its own, and its mere
    // existence was a real bug - ReviewService::assertEligible() (and the
    // frontend's own review-prompt check) only accept a status of exactly
    // Delivered, not "Delivered or later", so an order pushed on to
    // Completed silently lost its customer's ability to review it. Delivered
    // is now the terminal happy-path status instead (see
    // OrderStatusService::TRANSITIONS) - every order that had already
    // reached Completed was moved back to Delivered by
    // 2026_09_27_000000_revert_completed_orders_to_delivered before this
    // took effect. The case stays here on purpose (same reasoning as
    // ShippingCarrier::Econt/Processing above): Eloquent's native enum cast
    // throws - not silently nulls - when a persisted value has no matching
    // case, so removing it outright would 500 every read of any
    // order_status_histories row that still records it from before that
    // migration ran.
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
