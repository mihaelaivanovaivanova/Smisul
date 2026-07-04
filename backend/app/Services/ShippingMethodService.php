<?php

namespace App\Services;

use App\DataTransferObjects\Shipping\ShippingMethodData;

/**
 * Thin facade kept for backward compatibility with CheckoutController and
 * OrderService — both were built against all()/find() in Sprint 5, before
 * real carrier integration existed. All actual carrier logic now lives
 * behind ShippingService/ShippingProviderInterface (see Sprint 8); this
 * class is just the seam so neither caller needed to change shape.
 */
class ShippingMethodService
{
    public function __construct(private readonly ShippingService $shipping) {}

    /**
     * @return list<ShippingMethodData>
     */
    public function all(): array
    {
        return $this->shipping->availableMethods();
    }

    public function find(string $carrier, ?string $deliveryType = null): ?ShippingMethodData
    {
        return $this->shipping->find($carrier, $deliveryType);
    }
}
