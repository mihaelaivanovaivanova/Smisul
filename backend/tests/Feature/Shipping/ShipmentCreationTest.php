<?php

namespace Tests\Feature\Shipping;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use App\Exceptions\Shipping\ShippingProviderException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Shipment;
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
        Http::fake(['api.speedy.bg/*' => Http::response(['id' => 'SPEEDY-TEST-123', 'clientId' => 12345, 'client' => ['clientName' => 'Test Sender Co']])]);

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
        Http::fake(['api.speedy.bg/*' => Http::response(['id' => 'SPEEDY-TEST-1', 'clientId' => 12345, 'client' => ['clientName' => 'Test Sender Co']])]);

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
        OrderItem::factory()->for($order)->create();

        $shipment = $this->app->make(ShippingService::class)->createShipment($order);

        $this->assertSame('BN-TEST-1', $shipment->tracking_number);
        $this->assertSame('locker-42', $shipment->office_id);

        Http::assertSent(function ($request) use ($order) {
            return str_contains($request->url(), 'delivery-requests')
                && $request['orderNumber'] === $order->order_number
                && $request['paymentMode'] === 'prepaid'
                && $request['destination']['locationId'] === 'locker-42'
                // '2' is this account's real numeric any-apm wildcard
                // origin id (confirmed via GET /origins) - the literal
                // string "any-apm" fails BOX NOW's schema (P400).
                && $request['origin']['locationId'] === '2'
                // Confirmed against the guide ("allowReturn: Винаги трябва
                // да бъде false") and partner_api_1.72.yaml
                // (items.compartmentSize required for any-apm origin).
                && $request['allowReturn'] === false
                && $request['items'][0]['compartmentSize'] === 2;
        });
    }

    /**
     * Cash on delivery can no longer be newly selected (see
     * PaymentMethod::active()), but this covers a historical order placed
     * before it was removed — its Payment row still carries
     * payment_method = cash_on_delivery (the enum case is kept exactly
     * for this), and an admin creating its shipment must still send
     * BOX NOW the right paymentMode. Confirmed against BOX NOW's guide
     * v1.69 (section 4.4/4.8): "cod" is the real paymentMode value, and
     * amountToBeCollected must carry the amount their courier actually
     * collects — not the "0.00" every prepaid shipment sends.
     */
    #[Test]
    public function creating_a_box_now_shipment_sends_cod_and_the_real_amount_to_collect_for_a_historical_cash_on_delivery_order(): void
    {
        Http::fake([
            'api-production.boxnow.bg/api/v1/auth-sessions' => Http::response(['access_token' => 'test-token', 'token_type' => 'Bearer', 'expires_in' => 3600]),
            'api-production.boxnow.bg/api/v1/delivery-requests' => Http::response([
                'id' => 'DR-TEST-2',
                'parcels' => [['id' => 'BN-TEST-2']],
            ]),
        ]);

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::BoxNow,
            'shipping_delivery_type' => ShippingDeliveryType::Locker,
            'shipping_office_id' => 'locker-42',
            'shipping_office_name' => 'BOX NOW Sofia Mall',
            'grand_total' => 27.98,
        ]);
        Payment::factory()->for($order)->create([
            'payment_method' => PaymentMethod::CashOnDelivery,
            'provider' => PaymentProvider::CashOnDelivery,
        ]);

        $this->app->make(ShippingService::class)->createShipment($order);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'delivery-requests')
                && $request['paymentMode'] === 'cod'
                && $request['amountToBeCollected'] === '27.98';
        });
    }

    /**
     * Speedy's real `service.additionalServices.cod` shape (confirmed live
     * against the sandbox, complete with the real `codPremium` surcharge
     * Speedy's own price breakdown adds for it) — amount is the order's
     * grand_total (already includes shipping, see OrderService::placeOrder()),
     * with includeShippingPrice false so Speedy doesn't add its own
     * delivery fee on top of that.
     */
    #[Test]
    public function creating_a_speedy_shipment_sends_cod_for_a_cash_on_delivery_order(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response(['id' => 'SPEEDY-COD-1', 'clientId' => 12345, 'client' => ['clientName' => 'Test Sender Co']])]);

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::Speedy,
            'shipping_delivery_type' => ShippingDeliveryType::Address,
            'shipping_office_id' => null,
            'currency' => 'EUR',
            'grand_total' => 27.98,
        ]);
        Payment::factory()->for($order)->create([
            'payment_method' => PaymentMethod::CashOnDelivery,
            'provider' => PaymentProvider::CashOnDelivery,
        ]);

        $this->app->make(ShippingService::class)->createShipment($order);

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/shipment')) {
                return false;
            }

            $cod = $request['service']['additionalServices']['cod'];
            $receiptItem = $cod['fiscalReceiptItems'][0];

            return $cod['amount'] === 27.98
                && $cod['currencyCode'] === 'EUR'
                && $cod['processingType'] === 'CASH'
                && $cod['includeShippingPrice'] === false
                // Cyrillic "А", not Latin "A" — Speedy rejects the Latin
                // one outright (confirmed live), and this Company isn't
                // ДДС-registered, so it's always the 0% group.
                && $receiptItem['vatGroup'] === 'А'
                && $receiptItem['amount'] === 27.98
                && $receiptItem['amountWithVat'] === 27.98;
        });
    }

    #[Test]
    public function creating_a_speedy_shipment_for_a_prepaid_order_sends_no_cod(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response(['id' => 'SPEEDY-PREPAID-1', 'clientId' => 12345, 'client' => ['clientName' => 'Test Sender Co']])]);

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::Speedy,
            'shipping_delivery_type' => ShippingDeliveryType::Address,
            'shipping_office_id' => null,
        ]);
        Payment::factory()->for($order)->create(['payment_method' => PaymentMethod::Card]);

        $this->app->make(ShippingService::class)->createShipment($order);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/shipment')
                && ! array_key_exists('additionalServices', $request['service']);
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
        Http::fake(['api.speedy.bg/*' => Http::response(['id' => 'SPEEDY-TEST-1', 'clientId' => 12345, 'client' => ['clientName' => 'Test Sender Co']])]);

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::Speedy,
            'shipping_delivery_type' => ShippingDeliveryType::Address,
            'shipping_office_id' => null,
            'shipping_address_line' => 'ul. Vitosha 25A',
        ]);

        $shipment = $this->app->make(ShippingService::class)->createShipment($order);

        $this->assertSame('SPEEDY-TEST-1', $shipment->tracking_number);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/shipment')
                && $request['recipient']['address']['streetName'] === 'ul. Vitosha'
                && $request['recipient']['address']['streetNo'] === '25A'
                && $request['service']['serviceId'] === 505;
        });
    }

    #[Test]
    public function creating_a_speedy_shipment_falls_back_to_a_placeholder_number_when_the_address_has_none(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response(['id' => 'SPEEDY-TEST-2', 'clientId' => 12345, 'client' => ['clientName' => 'Test Sender Co']])]);

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::Speedy,
            'shipping_delivery_type' => ShippingDeliveryType::Address,
            'shipping_office_id' => null,
            'shipping_address_line' => 'ul. Vitosha',
        ]);

        $this->app->make(ShippingService::class)->createShipment($order);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/shipment')
                && $request['recipient']['address']['streetName'] === 'ul. Vitosha'
                && $request['recipient']['address']['streetNo'] === '0';
        });
    }

    /**
     * Speedy rejects `shipment/cancel` with fewer than 4 characters in
     * `comment`, despite the schema marking it optional - confirmed live.
     * Nothing upstream of SpeedyShippingProvider is guaranteed to supply a
     * reason at all (the admin "Cancel shipment" prompt can be left
     * blank/dismissed), so no reason must still produce a request Speedy
     * accepts.
     */
    #[Test]
    public function cancelling_a_speedy_shipment_with_no_reason_still_sends_a_valid_comment(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response([])]);

        $order = Order::factory()->create(['shipping_carrier' => ShippingCarrier::Speedy]);
        $shipment = Shipment::factory()->for($order)->created()->create(['tracking_number' => 'SPEEDY-CANCEL-1']);

        $this->app->make(ShippingService::class)->cancelShipment($shipment);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/shipment/cancel')
                && $request['shipmentId'] === 'SPEEDY-CANCEL-1'
                && mb_strlen((string) $request['comment']) >= 4;
        });
    }

    #[Test]
    public function cancelling_a_speedy_shipment_forwards_a_real_reason_as_the_comment(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response([])]);

        $order = Order::factory()->create(['shipping_carrier' => ShippingCarrier::Speedy]);
        $shipment = Shipment::factory()->for($order)->created()->create(['tracking_number' => 'SPEEDY-CANCEL-2']);

        $this->app->make(ShippingService::class)->cancelShipment($shipment, 'Customer requested cancellation');

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/shipment/cancel')
                && $request['comment'] === 'Customer requested cancellation';
        });
    }
}
