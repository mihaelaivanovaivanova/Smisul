<?php

namespace App\DataTransferObjects\Checkout;

use App\Enums\ShippingCarrier;

/**
 * A generic, flat-rate stand-in for a real carrier quote (see
 * ShippingMethodService). Deliberately shaped like what a real Econt/
 * Speedy/BOX NOW rate-quote API would eventually return — carrier + label
 * + price — so wiring in real quotes later only changes where these values
 * come from, not how the rest of checkout consumes them.
 */
final readonly class ShippingMethodData
{
    public function __construct(
        public ShippingCarrier $carrier,
        public string $label,
        public string $description,
        public float $price,
        public string $currency,
    ) {}
}
