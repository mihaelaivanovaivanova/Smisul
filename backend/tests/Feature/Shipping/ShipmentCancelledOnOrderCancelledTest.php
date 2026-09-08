<?php

namespace Tests\Feature\Shipping;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingCarrier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers Listeners\CancelShipmentOnOrderCancelled — the automatic trigger
 * that cancels a shipment with the carrier the moment an admin cancels the
 * order, mirroring ShipmentCreatedOnPaymentTest for the opposite direction.
 */
class ShipmentCancelledOnOrderCancelledTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cancelling_an_order_automatically_cancels_its_box_now_shipment(): void
    {
        Http::fake([
            'api-production.boxnow.bg/api/v1/auth-sessions' => Http::response(['access_token' => 'test-token', 'token_type' => 'Bearer', 'expires_in' => 3600]),
            'api-production.boxnow.bg/api/v1/parcels/BN-CANCEL-1:cancel' => Http::response([], 200),
        ]);

        $order = Order::factory()->create(['status' => OrderStatus::Paid, 'shipping_carrier' => ShippingCarrier::BoxNow]);
        OrderItem::factory()->for($order)->create();
        Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::BoxNow,
            'tracking_number' => 'BN-CANCEL-1',
        ]);

        $this->app->make(OrderStatusService::class)->transitionTo($order, OrderStatus::Cancelled, changedBy: null);

        $this->assertSame(ShipmentStatus::Cancelled, $order->shipment->fresh()->status);
    }

    #[Test]
    public function a_failed_carrier_cancellation_does_not_prevent_the_order_from_being_cancelled(): void
    {
        Log::shouldReceive('error')->once();
        Http::fake([
            'api-production.boxnow.bg/api/v1/auth-sessions' => Http::response(['access_token' => 'test-token', 'token_type' => 'Bearer', 'expires_in' => 3600]),
            'api-production.boxnow.bg/api/v1/parcels/BN-CANCEL-2:cancel' => Http::response(['code' => 'already-delivered'], 403),
        ]);

        $order = Order::factory()->create(['status' => OrderStatus::Paid, 'shipping_carrier' => ShippingCarrier::BoxNow]);
        OrderItem::factory()->for($order)->create();
        Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::BoxNow,
            'tracking_number' => 'BN-CANCEL-2',
        ]);

        $updated = $this->app->make(OrderStatusService::class)->transitionTo($order, OrderStatus::Cancelled, changedBy: null);

        $this->assertSame(OrderStatus::Cancelled, $updated->status, 'The order cancellation must succeed even when the carrier call fails.');
        $this->assertSame(ShipmentStatus::Accepted, $order->shipment->fresh()->status, 'The shipment status must not change when the carrier rejected the cancellation.');
    }

    #[Test]
    public function cancelling_an_order_with_no_shipment_does_nothing(): void
    {
        Http::fake();

        $order = Order::factory()->create(['status' => OrderStatus::Paid]);
        OrderItem::factory()->for($order)->create();

        $this->app->make(OrderStatusService::class)->transitionTo($order, OrderStatus::Cancelled, changedBy: null);

        Http::assertNothingSent();
    }

    #[Test]
    public function a_non_cancellation_status_transition_does_not_cancel_the_shipment(): void
    {
        Http::fake();

        $order = Order::factory()->create(['status' => OrderStatus::Paid, 'shipping_carrier' => ShippingCarrier::BoxNow]);
        OrderItem::factory()->for($order)->create();
        Shipment::factory()->for($order)->created()->create(['carrier' => ShippingCarrier::BoxNow]);

        $this->app->make(OrderStatusService::class)->transitionTo($order, OrderStatus::Processing, changedBy: null);

        Http::assertNothingSent();
        $this->assertSame(ShipmentStatus::Accepted, $order->shipment->fresh()->status);
    }
}
