<?php

namespace App\Enums;

/**
 * How the parcel reaches the customer for a given carrier — not every
 * carrier supports every type (see each ShippingProviderInterface
 * implementation's supportedDeliveryTypes()). BOX NOW is locker-only;
 * Speedy supports a staffed office, an automated machine (also modeled as
 * Locker — same self-service pickup pattern, just a different carrier —
 * see ShippingService::label() for how the two carriers' Locker options
 * stay distinguishable at checkout), and home delivery.
 */
enum ShippingDeliveryType: string
{
    case Office = 'office';
    case Locker = 'locker';
    case Address = 'address';

    public function label(): string
    {
        return match ($this) {
            self::Office => 'До офис',
            self::Locker => 'До автомат',
            self::Address => 'До адрес',
        };
    }

    public function requiresOfficeSelection(): bool
    {
        return $this !== self::Address;
    }
}
