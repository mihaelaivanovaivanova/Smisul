<?php

namespace App\Contracts;

use App\DataTransferObjects\Shipping\ShipmentData;
use App\DataTransferObjects\Shipping\ShippingOfficeData;
use App\DataTransferObjects\Shipping\ShippingQuoteData;
use App\DataTransferObjects\Shipping\ShippingQuoteRequestData;
use App\DataTransferObjects\Shipping\TrackingData;
use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use App\Models\Order;

/**
 * Every courier integration (Econt, Speedy, BOX NOW, and any future
 * addition) implements this so ShippingService never needs carrier-specific
 * logic — mirrors PaymentGatewayInterface in the payments domain. Each
 * implementation is independent: no shared base class, so replacing or
 * retiring one carrier never touches another.
 */
interface ShippingProviderInterface
{
    public function carrier(): ShippingCarrier;

    /**
     * @return list<ShippingDeliveryType>
     */
    public function supportedDeliveryTypes(): array;

    /**
     * The carrier's advertised flat rate for a delivery type — no network
     * call, used to populate the checkout catalog (see
     * ShippingService::availableMethods()) and as quote()'s fallback when
     * the live rate call fails.
     */
    public function baseRate(ShippingDeliveryType $deliveryType): ShippingQuoteData;

    /**
     * A live, destination-aware price/eta. Implementations should fall back
     * to baseRate() rather than throwing when the carrier's API is
     * unreachable, so checkout never breaks on a flaky sandbox.
     */
    public function quote(ShippingQuoteRequestData $request): ShippingQuoteData;

    /**
     * @return list<ShippingOfficeData>
     */
    public function offices(?string $city = null): array;

    /**
     * Creates a real shipment with the carrier. Unlike quote(), this does
     * not fall back silently on failure — a fabricated tracking number
     * would be worse than an explicit error (see
     * App\Exceptions\Shipping\ShippingProviderException).
     */
    public function createShipment(Order $order, ShippingDeliveryType $deliveryType, ?string $officeId): ShipmentData;

    public function track(string $trackingNumber): TrackingData;
}
