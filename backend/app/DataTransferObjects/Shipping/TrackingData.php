<?php

namespace App\DataTransferObjects\Shipping;

use App\Enums\ShipmentStatus;
use Carbon\CarbonImmutable;

/**
 * A gateway-agnostic view of a shipment's tracking history, normalized by
 * each provider's own track() implementation from its carrier-specific
 * payload shape (see ShippingProviderInterface).
 */
final readonly class TrackingData
{
    /**
     * @param  list<TrackingEventData>  $events
     */
    public function __construct(
        public ShipmentStatus $currentStatus,
        public array $events,
        public ?CarbonImmutable $estimatedDeliveryAt,
    ) {}
}
