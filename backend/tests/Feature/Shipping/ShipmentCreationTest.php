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
        Http::fake([
            'demo.econt.com/*' => Http::response([
                'trackingNumber' => 'ECONT-TEST-123',
                'labelUrl' => 'https://demo.econt.com/labels/123.pdf',
            ]),
        ]);

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::Econt,
            'shipping_delivery_type' => ShippingDeliveryType::Address,
            'shipping_office_id' => null,
            'shipping_office_name' => null,
        ]);

        $shipment = $this->app->make(ShippingService::class)->createShipment($order);

        $this->assertSame('ECONT-TEST-123', $shipment->tracking_number);
        $this->assertSame(ShipmentStatus::Accepted, $shipment->status);
        $this->assertSame('https://demo.econt.com/labels/123.pdf', $shipment->label_url);

        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'tracking_number' => 'ECONT-TEST-123',
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
        Http::fake(['demo.econt.com/*' => Http::response(['error' => 'invalid address'], 422)]);

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::Econt,
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
        Http::fake(['demo.econt.com/*' => Http::response(['trackingNumber' => 'ECONT-TEST-1'])]);

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::Econt,
            'shipping_delivery_type' => ShippingDeliveryType::Address,
        ]);

        $service = $this->app->make(ShippingService::class);
        $service->createShipment($order);

        $this->expectException(RuntimeException::class);
        $service->createShipment($order->fresh());
    }

    #[Test]
    public function creating_a_box_now_shipment_uses_the_selected_locker(): void
    {
        Http::fake([
            'sandbox-api.boxnow.bg/oauth/*' => Http::response(['access_token' => 'test-token']),
            'sandbox-api.boxnow.bg/v1/parcels' => Http::response(['trackingNumber' => 'BN-TEST-1']),
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
    }
}
