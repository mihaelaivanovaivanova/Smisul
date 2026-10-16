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
     * among several operations returned for the same tracking call. Only
     * Delivered/Failed/Cancelled qualify, not Returned, and not by
     * oversight: a real production case needed exactly this distinction.
     *
     * Delivered needs it - Speedy logs a post-delivery "declined
     * inspection/testing" operation for every COD shipment (OBPD is
     * always enabled for those, see SpeedyShippingProvider::
     * createShipment()), and naively trusting whichever operation is
     * simply last in the list let that unmapped trailing one hide the
     * real delivery operation it followed.
     *
     * Returned must NOT get the same treatment - confirmed live, Speedy's
     * own "Връщане в офис" ("returned to office") tracking event can fire
     * mid-journey, as a retry/redirect step, not a true final return (the
     * carrier kept re-attempting delivery for days afterward in a real
     * case). Treating Returned as equally "sticky" would make
     * ShipmentTrackingSyncService's Returned -> Shipped self-correction
     * (see its own docblock) unreachable in practice, since nothing
     * chronologically after it could ever "win" again. Leaving it out
     * means a real subsequent status (even back to InTransit) is simply
     * whatever's genuinely last, the same as before this method existed -
     * see isSticky()'s only caller for exactly how.
     */
    public function isSticky(): bool
    {
        return match ($this) {
            self::Delivered, self::Failed, self::Cancelled => true,
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
