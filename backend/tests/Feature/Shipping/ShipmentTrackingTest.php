<?php

namespace Tests\Feature\Shipping;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingCarrier;
use App\Exceptions\Shipping\ShippingProviderException;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShipmentTrackingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tracking_a_shipment_records_a_new_status_event_when_the_status_changed(): void
    {
        Http::fake([
            'api.speedy.bg/*' => Http::response([
                'parcels' => [[
                    'operations' => [
                        ['operationCode' => 39, 'dateTime' => '2026-07-06T10:00:00+03:00', 'description' => null],
                        ['operationCode' => 1, 'dateTime' => '2026-07-06T14:00:00+03:00', 'description' => null],
                    ],
                ]],
            ]),
        ]);

        $order = Order::factory()->create(['shipping_carrier' => ShippingCarrier::Speedy]);
        $shipment = Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::PickedUp,
        ]);

        $tracking = $this->app->make(ShippingService::class)->track($shipment);

        $this->assertSame(ShipmentStatus::InTransit, $tracking->currentStatus);
        $this->assertCount(2, $tracking->events);
        // Speedy's track endpoint never returns an ETA (see
        // SpeedyShippingProvider::track()).
        $this->assertNull($tracking->estimatedDeliveryAt);

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::InTransit, $shipment->status);
        $this->assertDatabaseHas('shipment_status_events', ['shipment_id' => $shipment->id, 'status' => 'in_transit']);
    }

    #[Test]
    public function tracking_does_not_record_a_duplicate_event_when_the_status_is_unchanged(): void
    {
        Http::fake([
            'api.speedy.bg/*' => Http::response([
                'parcels' => [[
                    'operations' => [['operationCode' => 39, 'dateTime' => '2026-07-06T10:00:00+03:00']],
                ]],
            ]),
        ]);

        $order = Order::factory()->create(['shipping_carrier' => ShippingCarrier::Speedy]);
        $shipment = Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::PickedUp,
        ]);

        $this->app->make(ShippingService::class)->track($shipment);

        $this->assertDatabaseCount('shipment_status_events', 0);
    }

    #[Test]
    public function tracking_throws_when_the_carrier_api_fails(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response(null, 500)]);

        $order = Order::factory()->create(['shipping_carrier' => ShippingCarrier::Speedy]);
        $shipment = Shipment::factory()->for($order)->created()->create(['carrier' => ShippingCarrier::Speedy]);

        $this->expectException(ShippingProviderException::class);

        $this->app->make(ShippingService::class)->track($shipment);
    }

    #[Test]
    public function a_guest_can_view_their_order_shipment_via_the_access_token(): void
    {
        $order = Order::factory()->create();
        $shipment = Shipment::factory()->for($order)->created()->create();

        $response = $this->getJson("/api/v1/orders/{$order->id}/shipment?token={$order->guest_access_token}");

        $response->assertOk();
        $response->assertJsonPath('data.tracking_number', $shipment->tracking_number);
        $response->assertJsonPath('data.status', $shipment->status->value);
    }

    #[Test]
    public function a_guest_cannot_view_a_shipment_without_the_correct_token(): void
    {
        $order = Order::factory()->create();
        Shipment::factory()->for($order)->created()->create();

        $this->getJson("/api/v1/orders/{$order->id}/shipment")->assertForbidden();
        $this->getJson("/api/v1/orders/{$order->id}/shipment?token=wrong")->assertForbidden();
    }

    #[Test]
    public function viewing_a_shipment_for_an_order_with_none_yet_returns_404(): void
    {
        $order = Order::factory()->create();

        $response = $this->getJson("/api/v1/orders/{$order->id}/shipment?token={$order->guest_access_token}");

        $response->assertNotFound();
    }
}
