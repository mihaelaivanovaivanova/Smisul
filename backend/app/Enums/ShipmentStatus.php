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
     * How far along the shipment lifecycle this status represents - used
     * to pick the most-advanced real status among several operations
     * returned for the same tracking call (see SpeedyShippingProvider::
     * track()), since a trailing operation this app doesn't recognize
     * (mapped to Pending by a mapStatus()'s default branch) must never
     * hide or "undo" a real status reported earlier in the same response.
     * A real production case this fixes: Speedy logs a post-delivery
     * "declined inspection/testing" operation for every COD shipment
     * (OBPD is always enabled for those, see SpeedyShippingProvider::
     * createShipment()) - picking whichever operation is simply last in
     * the list wrongly picked that unmapped trailing one over the real
     * delivery operation it followed.
     */
    public function progressRank(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Accepted => 1,
            self::Prepared => 2,
            self::PickedUp => 3,
            self::InTransit => 4,
            self::OutForDelivery => 5,
            self::ReadyForPickup => 6,
            self::Delivered, self::Returned, self::Failed, self::Cancelled => 7,
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
