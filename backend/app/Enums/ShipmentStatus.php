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
    case Delivered = 'delivered';
    case Returned = 'returned';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return match ($this) {
            self::Delivered, self::Returned, self::Failed => true,
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
            self::Delivered => 'Доставена',
            self::Returned => 'Върната',
            self::Failed => 'Неуспешна доставка',
        };
    }
}
