<?php

namespace App\DataTransferObjects\Shipping;

use App\Enums\ShipmentStatus;
use Carbon\CarbonImmutable;

final readonly class TrackingEventData
{
    public function __construct(
        public ShipmentStatus $status,
        public ?string $description,
        public CarbonImmutable $occurredAt,
    ) {}
}
