<?php

namespace App\DataTransferObjects\Shipping;

use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;

/**
 * A pickup point a customer can choose at checkout — a Speedy office or a
 * BOX NOW locker. `id` is the carrier's own identifier, echoed back
 * verbatim in ShippingProviderInterface::createShipment().
 */
final readonly class ShippingOfficeData
{
    public function __construct(
        public string $id,
        public ShippingCarrier $carrier,
        public ShippingDeliveryType $type,
        public string $name,
        public string $city,
        public string $address,
    ) {}
}
