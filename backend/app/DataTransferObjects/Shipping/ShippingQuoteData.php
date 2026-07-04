<?php

namespace App\DataTransferObjects\Shipping;

use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;

/**
 * The result of pricing a concrete destination — what
 * ShippingProviderInterface::quote() returns, whether from a live carrier
 * rate call or (on failure) the provider's own flat-rate fallback.
 */
final readonly class ShippingQuoteData
{
    public function __construct(
        public ShippingCarrier $carrier,
        public ShippingDeliveryType $deliveryType,
        public float $price,
        public string $currency,
        public string $estimatedDelivery,
    ) {}
}
