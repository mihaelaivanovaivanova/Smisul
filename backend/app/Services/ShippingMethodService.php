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
    public function __construct(
        private readonly ShippingService $shipping,
        private readonly SettingService $settings,
    ) {}

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

    /**
     * Whole-EUR cart subtotal that unlocks free delivery, store-wide across
     * every carrier — the same admin-configurable general.free_shipping_threshold
     * the cart drawer's progress bar already advertises pre-checkout
     * (CartDrawer.tsx). Null/0 disables it, same "0 = off" convention as
     * every other threshold setting.
     */
    public function freeShippingThreshold(): ?float
    {
        $raw = $this->settings->get('general.free_shipping_threshold');
        $threshold = $raw !== null && $raw !== '' ? (float) $raw : null;

        return $threshold !== null && $threshold > 0 ? $threshold : null;
    }

    public function isFreeShipping(float $subtotal): bool
    {
        $threshold = $this->freeShippingThreshold();

        return $threshold !== null && $subtotal >= $threshold;
    }

    /**
     * Same as all(), but with every method's price zeroed once the cart
     * subtotal clears the free-shipping threshold — what checkout actually
     * shows/charges, as opposed to the raw catalog rate. Never touches a
     * COD fee: that's a separate, payment-method-level charge
     * (PaymentService::feeFor()) that still applies on top regardless.
     *
     * @return list<ShippingMethodData>
     */
    public function allForSubtotal(float $subtotal): array
    {
        $methods = $this->all();

        if (! $this->isFreeShipping($subtotal)) {
            return $methods;
        }

        return array_map(
            fn (ShippingMethodData $method) => new ShippingMethodData(
                carrier: $method->carrier,
                deliveryType: $method->deliveryType,
                label: $method->label,
                description: $method->description,
                price: 0.0,
                currency: $method->currency,
                estimatedDelivery: $method->estimatedDelivery,
                requiresOffice: $method->requiresOffice,
            ),
            $methods,
        );
    }
}
