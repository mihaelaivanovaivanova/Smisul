<?php

namespace Tests\Feature\Shipping;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use App\Exceptions\Shipping\ShippingProviderException;
use App\Models\Order;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ShipmentCreationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_a_shipment_persists_a_tracking_number_and_status_event(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response(['id' => 'SPEEDY-TEST-123'])]);

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::Speedy,
            'shipping_delivery_type' => ShippingDeliveryType::Address,
            'shipping_office_id' => null,
            'shipping_office_name' => null,
        ]);

        $shipment = $this->app->make(ShippingService::class)->createShipment($order);

        $this->assertSame('SPEEDY-TEST-123', $shipment->tracking_number);
        $this->assertSame(ShipmentStatus::Accepted, $shipment->status);
        $this->assertNull($shipment->label_url);

        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'tracking_number' => 'SPEEDY-TEST-123',
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('shipment_status_events', [
            'shipment_id' => $shipment->id,
            'status' => 'accepted',
        ]);
    }

    #[Test]
    public function creating_a_shipment_throws_when_the_carrier_rejects_the_request(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response(['error' => 'invalid address'], 422)]);

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::Speedy,
            'shipping_delivery_type' => ShippingDeliveryType::Address,
        ]);

        $this->expectException(ShippingProviderException::class);

        try {
            $this->app->make(ShippingService::class)->createShipment($order);
        } finally {
            $this->assertDatabaseMissing('shipments', ['order_id' => $order->id]);
        }
    }

    #[Test]
    public function a_shipment_cannot_be_created_twice_for_the_same_order(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response(['id' => 'SPEEDY-TEST-1'])]);

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::Speedy,
            'shipping_delivery_type' => ShippingDeliveryType::Address,
        ]);

        $service = $this->app->make(ShippingService::class);
        $service->createShipment($order);

        $this->expectException(RuntimeException::class);
        $service->createShipment($order->fresh());
    }

    /**
     * The fake response mirrors BOX NOW's real, confirmed shape (see their
     * partner API guide, https://boxnow.bg/partner-api) — the top-level
     * "id" is the delivery-request's own reference; the parcel's own id
     * (parcels.0.id) is what's actually used as our tracking number, since
     * that's what every other endpoint (tracking, label) is keyed by.
     */
    #[Test]
    public function creating_a_box_now_shipment_uses_the_selected_locker(): void
    {
        Http::fake([
            'api-production.boxnow.bg/api/v1/auth-sessions' => Http::response(['access_token' => 'test-token', 'token_type' => 'Bearer', 'expires_in' => 3600]),
            'api-production.boxnow.bg/api/v1/delivery-requests' => Http::response([
                'id' => 'DR-TEST-1',
                'parcels' => [['id' => 'BN-TEST-1']],
            ]),
        ]);

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::BoxNow,
            'shipping_delivery_type' => ShippingDeliveryType::Locker,
            'shipping_office_id' => 'locker-42',
            'shipping_office_name' => 'BOX NOW Sofia Mall',
        ]);

        $shipment = $this->app->make(ShippingService::class)->createShipment($order);

        $this->assertSame('BN-TEST-1', $shipment->tracking_number);
        $this->assertSame('locker-42', $shipment->office_id);

        Http::assertSent(function ($request) use ($order) {
            return str_contains($request->url(), 'delivery-requests')
                && $request['orderNumber'] === $order->order_number
                && $request['paymentMode'] === 'prepaid'
                && $request['destination']['locationId'] === 'locker-42'
                && $request['origin']['locationId'] === 'any-apm';
        });
    }

    /**
     * Speedy's real API (confirmed against the sandbox with live test
     * credentials) requires a non-empty recipient.address.streetNo — a
     * single free-text address line has to be split into street name +
     * number before it's sent. See SpeedyShippingProvider::splitStreetAndNumber.
     */
    #[Test]
    public function creating_a_speedy_shipment_splits_the_address_line_into_street_and_number(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response(['id' => 'SPEEDY-TEST-1'])]);

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::Speedy,
            'shipping_delivery_type' => ShippingDeliveryType::Address,
            'shipping_office_id' => null,
            'shipping_address_line' => 'ul. Vitosha 25A',
        ]);

        $shipment = $this->app->make(ShippingService::class)->createShipment($order);

        $this->assertSame('SPEEDY-TEST-1', $shipment->tracking_number);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.speedy.bg')
                && $request['recipient']['address']['streetName'] === 'ul. Vitosha'
                && $request['recipient']['address']['streetNo'] === '25A'
                && $request['service']['serviceId'] === 505;
        });
    }

    #[Test]
    public function creating_a_speedy_shipment_falls_back_to_a_placeholder_number_when_the_address_has_none(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response(['id' => 'SPEEDY-TEST-2'])]);

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::Speedy,
            'shipping_delivery_type' => ShippingDeliveryType::Address,
            'shipping_office_id' => null,
            'shipping_address_line' => 'ul. Vitosha',
        ]);

        $this->app->make(ShippingService::class)->createShipment($order);

        Http::assertSent(function ($request) {
            return $request['recipient']['address']['streetName'] === 'ul. Vitosha'
                && $request['recipient']['address']['streetNo'] === '0';
        });
    }
}
