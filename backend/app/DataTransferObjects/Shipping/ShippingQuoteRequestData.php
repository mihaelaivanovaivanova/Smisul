<?php

namespace App\DataTransferObjects\Shipping;

use App\Enums\ShippingDeliveryType;

/**
 * Input to ShippingProviderInterface::quote() — a concrete destination
 * (and, once known, weight/value) to price, as opposed to the flat catalog
 * rate ShippingMethodData carries.
 */
final readonly class ShippingQuoteRequestData
{
    public function __construct(
        public ShippingDeliveryType $deliveryType,
        public string $city,
        public string $postalCode,
        public ?float $weightKg = null,
        public ?float $declaredValue = null,
    ) {}
}
