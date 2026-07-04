<?php

namespace App\DataTransferObjects\Shipping;

use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;

/**
 * A single sellable (carrier, delivery type) combination shown at checkout,
 * with its advertised flat rate — built from each provider's baseRate(),
 * never a live network call (see ShippingService::availableMethods()), so
 * listing methods at checkout stays instant regardless of carrier API
 * reachability. A precise, destination-aware price is what
 * ShippingQuoteData (via the dedicated quote endpoint) is for.
 */
final readonly class ShippingMethodData
{
    public function __construct(
        public ShippingCarrier $carrier,
        public ShippingDeliveryType $deliveryType,
        public string $label,
        public string $description,
        public float $price,
        public string $currency,
        public string $estimatedDelivery,
        public bool $requiresOffice,
    ) {}
}
