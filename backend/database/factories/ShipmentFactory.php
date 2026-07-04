<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'carrier' => ShippingCarrier::Econt,
            'delivery_type' => ShippingDeliveryType::Office,
            'office_id' => 'office-1',
            'office_name' => 'Econt Office 1',
            'tracking_number' => null,
            'status' => ShipmentStatus::Pending,
            'price' => 5.99,
            'currency' => 'EUR',
            'estimated_delivery_at' => null,
            'label_url' => null,
            'raw_response' => null,
        ];
    }

    public function created(): static
    {
        return $this->state(fn (array $attributes) => [
            'tracking_number' => (string) Str::uuid(),
            'status' => ShipmentStatus::Accepted,
        ]);
    }
}
