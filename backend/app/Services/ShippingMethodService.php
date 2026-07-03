<?php

namespace App\Services;

use App\DataTransferObjects\Checkout\ShippingMethodData;
use App\Enums\Currency;
use App\Enums\ShippingCarrier;

/**
 * Generic, flat-rate shipping options — no carrier API is integrated (see
 * Sprint 5 scope). Real Econt/Speedy/BOX NOW rate quoting, office/locker
 * lookup, and label generation are future extension points: they'd live
 * behind this same all(...)/find(...) contract, most likely backed by an
 * HTTP client per carrier instead of the hardcoded list below. Nothing
 * calling this service (CheckoutController, OrderService) would need to
 * change shape when that happens.
 */
class ShippingMethodService
{
    /**
     * @return list<ShippingMethodData>
     */
    public function all(): array
    {
        return [
            new ShippingMethodData(
                carrier: ShippingCarrier::Econt,
                label: 'Econt',
                description: 'Доставка до офис или адрес с Econt.',
                price: 5.99,
                currency: Currency::EUR->value,
            ),
            new ShippingMethodData(
                carrier: ShippingCarrier::Speedy,
                label: 'Speedy',
                description: 'Доставка до офис или адрес със Speedy.',
                price: 5.99,
                currency: Currency::EUR->value,
            ),
            new ShippingMethodData(
                carrier: ShippingCarrier::BoxNow,
                label: 'BOX NOW',
                description: 'Доставка до автомат на BOX NOW.',
                price: 4.99,
                currency: Currency::EUR->value,
            ),
        ];
    }

    public function find(string $carrier): ?ShippingMethodData
    {
        foreach ($this->all() as $method) {
            if ($method->carrier->value === $carrier) {
                return $method;
            }
        }

        return null;
    }
}
