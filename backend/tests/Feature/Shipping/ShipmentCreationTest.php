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
     * Regression test for a real reported bug: a Bulgarian address
     * combining a city, a residential complex, and a boulevard ("гр.
     * Бургас, ж.к. Меден рудник, бул. Александър Георгиев -
     * Коджакафалията 278") made createShipment() fail outright, because
     * Speedy's real API rejects address.streetName once it passes 50
     * characters and nothing was capping it. Fixed by recognizing "ж.к."
     * and sending the complex name as its own real field (complexName -
     * confirmed against Speedy's published Address schema) instead of
     * leaving it jumbled inside streetName, which also means this
     * particular address no longer even needs truncating.
     */
    #[Test]
    public function creating_a_speedy_shipment_sends_the_residential_complex_as_its_own_field(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response(['id' => 'SPEEDY-COMPLEX-1', 'clientId' => 12345, 'client' => ['clientName' => 'Test Sender Co']])]);

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::Speedy,
            'shipping_delivery_type' => ShippingDeliveryType::Address,
            'shipping_office_id' => null,
            'shipping_address_line' => 'гр. Бургас, ж.к. Меден рудник, бул. Александър Георгиев - Коджакафалията 278',
        ]);

        $shipment = $this->app->make(ShippingService::class)->createShipment($order);

        $this->assertSame('SPEEDY-COMPLEX-1', $shipment->tracking_number);

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/shipment')) {
                return false;
            }

            $address = $request['recipient']['address'];

            return $address['complexName'] === 'Меден рудник'
                && $address['streetName'] === 'бул. Александър Георгиев - Коджакафалията'
                && $address['streetNo'] === '278';
        });
    }

    /**
     * Regression test for a second real reported bug found while verifying
     * the one above: an address ending "...бл. 5, ет. 2" made
     * splitStreetAndNumber() grab the trailing number (2, the *floor*) as
     * the street number, wrongly leaving "бл. 5" (the actual block number)
     * as junk text inside streetName. blockNo/floorNo are real, separate
     * Speedy Address fields - each recognized token is now pulled out
     * before the street/number split ever runs.
     */
    #[Test]
    public function creating_a_speedy_shipment_separates_block_entrance_floor_and_apartment_from_the_street(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response(['id' => 'SPEEDY-COMPONENTS-1', 'clientId' => 12345, 'client' => ['clientName' => 'Test Sender Co']])]);

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::Speedy,
            'shipping_delivery_type' => ShippingDeliveryType::Address,
            'shipping_office_id' => null,
            'shipping_address_line' => 'бул. Цар Борис III 41, бл. 2, вх. Б, ет. 4, ап. 12',
        ]);

        $this->app->make(ShippingService::class)->createShipment($order);

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/shipment')) {
                return false;
            }

            $address = $request['recipient']['address'];

            return $address['streetName'] === 'бул. Цар Борис III'
                && $address['streetNo'] === '41'
                && $address['blockNo'] === '2'
                && $address['entranceNo'] === 'Б'
                && $address['floorNo'] === '4'
                && $address['apartmentNo'] === '12';
        });
    }

    /**
     * The exact address that surfaced the block/floor mix-up above: no
     * street at all, just a residential complex and a block/floor - the
     * floor number (2) must not end up as streetNo just because it's the
     * last number in the line.
     */
    #[Test]
    public function a_complex_and_block_address_with_no_street_does_not_mistake_the_floor_for_the_street_number(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response(['id' => 'SPEEDY-COMPONENTS-2', 'clientId' => 12345, 'client' => ['clientName' => 'Test Sender Co']])]);

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::Speedy,
            'shipping_delivery_type' => ShippingDeliveryType::Address,
            'shipping_office_id' => null,
            'shipping_city' => 'гр. Приселци',
            'shipping_address_line' => 'гр. Приселци, ж.к. Дружба, бл. 5, ет. 2',
        ]);

        $this->app->make(ShippingService::class)->createShipment($order);

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/shipment')) {
                return false;
            }

            $address = $request['recipient']['address'];

            return $address['complexName'] === 'Дружба'
                && $address['blockNo'] === '5'
                && $address['floorNo'] === '2'
                && $address['streetNo'] === '0';
        });
    }

    /**
     * shipping_apartment (a dedicated column checkout already collects) is
     * used as a fallback for apartmentNo only when the free-text address
     * line didn't already spell one out with "ап." itself.
     */
    #[Test]
    public function the_dedicated_apartment_column_is_sent_when_the_address_line_has_no_apartment_of_its_own(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response(['id' => 'SPEEDY-APARTMENT-1', 'clientId' => 12345, 'client' => ['clientName' => 'Test Sender Co']])]);

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::Speedy,
            'shipping_delivery_type' => ShippingDeliveryType::Address,
            'shipping_office_id' => null,
            'shipping_address_line' => 'ul. Vitosha 25A',
            'shipping_apartment' => '7',
        ]);

        $this->app->make(ShippingService::class)->createShipment($order);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/shipment')
                && $request['recipient']['address']['apartmentNo'] === '7';
        });
    }

    /**
     * When even the last (most specific) segment alone still doesn't fit
     * under 50 characters, there's nothing left to drop - it falls back to
     * a hard character cut rather than sending nothing.
     */
    #[Test]
    public function a_street_name_that_still_does_not_fit_after_dropping_every_other_segment_is_hard_truncated(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response(['id' => 'SPEEDY-LONG-STREET-2', 'clientId' => 12345, 'client' => ['clientName' => 'Test Sender Co']])]);

        $longSingleSegment = str_repeat('ул', 40).' 5';

        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::Speedy,
            'shipping_delivery_type' => ShippingDeliveryType::Address,
            'shipping_office_id' => null,
            'shipping_address_line' => $longSingleSegment,
        ]);

        $this->app->make(ShippingService::class)->createShipment($order);

        Http::assertSent(function ($request) use ($longSingleSegment) {
            if (! str_ends_with($request->url(), '/shipment')) {
                return false;
            }

            $streetName = $request['recipient']['address']['streetName'];

            return mb_strlen($streetName) === 50
                && $streetName === mb_substr(str_repeat('ул', 40), 0, 50);
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
