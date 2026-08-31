<?php

namespace Tests\Feature\Shipping;

use App\Enums\OrderStatus;
use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers Listeners\CreateShipmentOnOrderPaid — the automatic trigger that
 * turns "payment confirmed" into a real carrier delivery-request, without
 * anyone having to click a button. See ShipmentCreationTest for
 * ShippingService::createShipment()'s own request/response correctness;
 * this file is about the trigger itself firing (and, just as importantly,
 * never being allowed to undo a confirmed payment).
 */
class ShipmentCreatedOnPaymentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function confirming_payment_automatically_creates_a_box_now_shipment(): void
    {
        Http::fake([
            'api-production.boxnow.bg/api/v1/auth-sessions' => Http::response(['access_token' => 'test-token', 'token_type' => 'Bearer', 'expires_in' => 3600]),
            'api-production.boxnow.bg/api/v1/delivery-requests' => Http::response([
                'id' => 'DR-AUTO-1',
                'parcels' => [['id' => 'BN-AUTO-1']],
            ]),
        ]);

        $order = Order::factory()->create([
            'status' => OrderStatus::Pending,
            'shipping_carrier' => ShippingCarrier::BoxNow,
            'shipping_delivery_type' => ShippingDeliveryType::Locker,
            'shipping_office_id' => 'locker-7',
            'shipping_office_name' => 'BOX NOW Test Locker',
        ]);
        OrderItem::factory()->for($order)->create();

        $this->app->make(OrderService::class)->confirmPayment($order);

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->shipment);
        $this->assertSame('BN-AUTO-1', $order->shipment->tracking_number);
    }

    #[Test]
    public function a_failed_box_now_call_does_not_prevent_the_payment_from_being_confirmed(): void
    {
        Log::shouldReceive('error')->once();
        Http::fake([
            'api-production.boxnow.bg/api/v1/auth-sessions' => Http::response(['access_token' => 'test-token', 'token_type' => 'Bearer', 'expires_in' => 3600]),
            'api-production.boxnow.bg/api/v1/delivery-requests' => Http::response(['message' => 'Address not found'], 400),
        ]);

        $order = Order::factory()->create([
            'status' => OrderStatus::Pending,
            'shipping_carrier' => ShippingCarrier::BoxNow,
            'shipping_delivery_type' => ShippingDeliveryType::Locker,
            'shipping_office_id' => 'locker-7',
            'shipping_office_name' => 'BOX NOW Test Locker',
        ]);
        OrderItem::factory()->for($order)->create();

        $this->app->make(OrderService::class)->confirmPayment($order);

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status, 'Payment confirmation must succeed even when the carrier call fails.');
        $this->assertNull($order->shipment, 'No shipment row should exist when the carrier rejected the request.');
    }

    #[Test]
    public function a_non_payment_status_transition_does_not_trigger_shipment_creation(): void
    {
        Http::fake();

        $order = Order::factory()->create([
            'status' => OrderStatus::Paid,
            'shipping_carrier' => ShippingCarrier::BoxNow,
            'shipping_delivery_type' => ShippingDeliveryType::Locker,
            'shipping_office_id' => 'locker-7',
        ]);
        OrderItem::factory()->for($order)->create();

        $this->app->make(OrderStatusService::class)->transitionTo($order, OrderStatus::Processing, changedBy: null);

        Http::assertNothingSent();
        $this->assertNull($order->fresh()->shipment);
    }
}
