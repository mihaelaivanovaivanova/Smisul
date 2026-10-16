<?php

namespace App\Enums;

/**
 * Lifecycle of a courier shipment, normalized across providers — each
 * ShippingProviderInterface::track() implementation maps its own carrier's
 * raw status vocabulary onto this shared set (see the mapStatus() private
 * method on each provider).
 */
enum ShipmentStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Prepared = 'prepared';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case ReadyForPickup = 'ready_for_pickup';
    case Delivered = 'delivered';
    case Returned = 'returned';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isFinal(): bool
    {
        return match ($this) {
            self::Delivered, self::Returned, self::Failed, self::Cancelled => true,
            default => false,
        };
    }

    /**
     * Whether this status, once observed anywhere in a shipment's
     * operation history, should always win as "the real current status"
     * regardless of what's reported chronologically after it - used by
     * SpeedyShippingProvider::track() to pick the right currentStatus
     * among several operations returned for the same tracking call.
     *
     * All four of Delivered/Returned/Failed/Cancelled are sticky.
     * Delivered needs it for a confirmed reason - Speedy logs a
     * post-delivery "declined inspection/testing" operation for every COD
     * shipment (OBPD is always enabled for those, see
     * SpeedyShippingProvider::createShipment()), and naively trusting
     * whichever operation is simply last in the list let that unmapped
     * trailing one hide the real delivery operation it followed.
     *
     * Returned was briefly excluded here (treating a trailing unmapped
     * operation as real "forward progress" that could un-stick it), to let
     * a premature Returned self-correct when Speedy's own "Връщане в офис"
     * fires mid-journey as a retry step rather than a true final return -
     * confirmed live in one real case (SQ369U). But a second real case
     * proved that sequence-based approach unsafe: a *genuinely* returned
     * order ("Връщане към подателя", its own reverse waybill) also has an
     * unmapped trailing operation after the Returned one, and with
     * Returned excluded here that trailing operation wrongly un-stuck a
     * *correct* Returned verdict back to Shipped. Both cases were the
     * exact same shape to the operation-code-only mapping used back then
     * (a recognized Returned code, then an unmapped one) with opposite
     * correct outcomes - undecidable from sequence alone.
     *
     * The real fix turned out to live one layer down, not here: Speedy's
     * own "Връщане в офис" and "Връщане към подателя" share the exact same
     * operationCode (38/111) and are only told apart by their description
     * text - confirmed live (SQ369U's raw operation history, "Връщане в
     * офис" at 38, never followed by an actual sender-return). See
     * SpeedyShippingProvider::mapStatus()'s own docblock - it now only
     * ever maps 38/111 to Returned when the description names the sender,
     * so Returned reaching this enum at all means a genuine final return.
     * Keeping Returned sticky here is therefore both safe and correct: a
     * genuine later Delivered can still self-correct it (an unambiguous
     * signal either way), and nothing ambiguous reaches Returned anymore
     * for isSticky() to have to arbitrate between.
     */
    public function isSticky(): bool
    {
        return match ($this) {
            self::Delivered, self::Returned, self::Failed, self::Cancelled => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Изчаква обработка',
            self::Accepted => 'Приета от куриера',
            self::Prepared => 'Подготвена за изпращане',
            self::PickedUp => 'Взета от куриера',
            self::InTransit => 'В транзит',
            self::OutForDelivery => 'За доставка',
            self::ReadyForPickup => 'Готова за получаване',
            self::Delivered => 'Доставена',
            self::Returned => 'Върната',
            self::Failed => 'Неуспешна доставка',
            self::Cancelled => 'Анулирана',
        };
    }
}
