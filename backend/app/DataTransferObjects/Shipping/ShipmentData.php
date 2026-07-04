<?php

namespace App\DataTransferObjects\Shipping;

use App\Enums\ShipmentStatus;

/**
 * What a provider hands back after creating a shipment — persisted onto the
 * Shipment model by ShippingService::createShipment(). labelUrl is a
 * reference to the carrier's own label document only (this sprint does not
 * implement automatic label printing).
 */
final readonly class ShipmentData
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public string $trackingNumber,
        public ShipmentStatus $status,
        public ?string $labelUrl,
        public array $rawResponse = [],
    ) {}
}
