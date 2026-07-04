<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentStatusEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentStatusEvent>
 */
class ShipmentStatusEventFactory extends Factory
{
    protected $model = ShipmentStatusEvent::class;

    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'status' => ShipmentStatus::Accepted,
            'description' => null,
            'occurred_at' => now(),
            'raw_payload' => null,
        ];
    }
}
