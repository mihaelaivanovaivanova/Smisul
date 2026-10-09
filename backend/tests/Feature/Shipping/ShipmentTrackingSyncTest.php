<?php

namespace Tests\Feature\Shipping;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingCarrier;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\ShipmentTrackingSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers ShipmentTrackingSyncService: the on-demand sync (the admin "Sync
 * tracking" button - see Admin\OrderController::syncShipmentTracking() -
 * or its manual CLI equivalent, SyncShipmentTracking) that polls every
 * Shipped order's shipment for its live carrier status and advances the
 * order to Delivered/Returned to match.
 */
class ShipmentTrackingSyncTest extends TestCase
{
    use RefreshDatabase;

    /** operationCode -14 is Speedy's own "delivered" code (see SpeedyShippingProvider::mapStatus()). */
    private function speedyDeliveredResponse(): array
    {
        return [
            'parcels' => [[
                'operations' => [
                    ['operationCode' => 1, 'dateTime' => '2026-07-06T10:00:00+03:00', 'description' => null],
                    ['operationCode' => -14, 'dateTime' => '2026-07-07T10:00:00+03:00', 'description' => null],
                ],
            ]],
        ];
    }

    /** operationCode 38 is Speedy's own "returned to sender" code. */
    private function speedyReturnedResponse(): array
    {
        return [
            'parcels' => [[
                'operations' => [
                    ['operationCode' => 1, 'dateTime' => '2026-07-06T10:00:00+03:00', 'description' => null],
                    ['operationCode' => 38, 'dateTime' => '2026-07-08T10:00:00+03:00', 'description' => null],
                ],
            ]],
        ];
    }

    /** BOX NOW's own "delivered" state, plus the auth-session token exchange its client() always makes first. */
    private function fakeBoxNowDelivered(): void
    {
        Http::fake([
            'api-production.boxnow.bg/api/v1/auth-sessions' => Http::response([
                'access_token' => 'test-token', 'token_type' => 'Bearer', 'expires_in' => 3600,
            ]),
            'api-production.boxnow.bg/api/v1/parcels*' => Http::response([
                'data' => [[
                    'state' => 'delivered',
                    'events' => [
                        ['type' => 'in-transit', 'locationDisplayName' => null, 'createTime' => '2026-07-06T10:00:00+03:00'],
                        ['type' => 'delivered', 'locationDisplayName' => null, 'createTime' => '2026-07-07T10:00:00+03:00'],
                    ],
                ]],
            ]),
        ]);
    }

    #[Test]
    public function a_shipped_order_advances_to_delivered_when_its_speedy_shipment_tracks_as_delivered(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response($this->speedyDeliveredResponse())]);

        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);
        $shipment = Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::InTransit,
        ]);

        $result = app(ShipmentTrackingSyncService::class)->sync();

        $this->assertSame(['checked' => 1, 'updated' => 1, 'orders_updated' => 1, 'failed' => 0], $result);
        $this->assertSame(ShipmentStatus::Delivered, $shipment->fresh()->status);
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => OrderStatus::Delivered->value,
            'previous_status' => OrderStatus::Shipped->value,
            'changed_by_user_id' => null,
        ]);
    }

    /** Same as above, but BOX NOW - confirms the sync is carrier-agnostic, not Speedy-only. */
    #[Test]
    public function a_shipped_order_advances_to_delivered_when_its_box_now_shipment_tracks_as_delivered(): void
    {
        $this->fakeBoxNowDelivered();

        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::BoxNow]);
        $shipment = Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::BoxNow,
            'status' => ShipmentStatus::InTransit,
        ]);

        $result = app(ShipmentTrackingSyncService::class)->sync();

        $this->assertSame(1, $result['orders_updated']);
        $this->assertSame(ShipmentStatus::Delivered, $shipment->fresh()->status);
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }

    #[Test]
    public function a_shipped_order_advances_to_returned_when_its_shipment_tracks_as_returned(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response($this->speedyReturnedResponse())]);

        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);
        Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::InTransit,
        ]);

        app(ShipmentTrackingSyncService::class)->sync();

        $this->assertSame(OrderStatus::Returned, $order->fresh()->status);
    }

    /**
     * Regression guard: an order that isn't (or is no longer) sitting at
     * exactly Shipped - a manual correction, or one a previous sync already
     * advanced - must never have its shipment polled at all, let alone have
     * its status fought over. Direct consequence of sync()'s own
     * whereHas('order', status = Shipped) filter.
     */
    #[Test]
    public function an_order_not_currently_shipped_has_its_shipment_left_untouched(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response($this->speedyDeliveredResponse())]);

        $order = Order::factory()->create(['status' => OrderStatus::Delivered, 'shipping_carrier' => ShippingCarrier::Speedy]);
        $shipment = Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::InTransit,
        ]);

        $result = app(ShipmentTrackingSyncService::class)->sync();

        $this->assertSame(0, $result['checked']);
        $this->assertSame(ShipmentStatus::InTransit, $shipment->fresh()->status);
        Http::assertNothingSent();
    }

    #[Test]
    public function a_shipment_already_at_a_final_status_is_not_queried_again(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);
        Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::Delivered,
        ]);

        Http::fake(['api.speedy.bg/*' => Http::response($this->speedyDeliveredResponse())]);

        $result = app(ShipmentTrackingSyncService::class)->sync();

        $this->assertSame(0, $result['checked']);
        Http::assertNothingSent();
    }

    #[Test]
    public function a_shipment_with_no_tracking_number_yet_is_skipped(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);
        Shipment::factory()->for($order)->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::Pending,
            'tracking_number' => null,
        ]);

        $result = app(ShipmentTrackingSyncService::class)->sync();

        $this->assertSame(0, $result['checked']);
    }

    #[Test]
    public function one_shipments_carrier_failure_does_not_stop_the_others_from_syncing(): void
    {
        $failingOrder = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);
        Shipment::factory()->for($failingOrder)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::InTransit,
        ]);

        $okOrder = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);
        Shipment::factory()->for($okOrder)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::InTransit,
        ]);

        Http::fake([
            'api.speedy.bg/*' => Http::sequence()
                ->push(['error' => ['message' => 'boom']], 500)
                ->push($this->speedyDeliveredResponse()),
        ]);

        $result = app(ShipmentTrackingSyncService::class)->sync();

        $this->assertSame(2, $result['checked']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['orders_updated']);
        $this->assertSame(OrderStatus::Delivered, $okOrder->fresh()->status);
        $this->assertSame(OrderStatus::Shipped, $failingOrder->fresh()->status);
    }

    #[Test]
    public function the_console_command_runs_the_sync_and_reports_a_summary(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response($this->speedyDeliveredResponse())]);

        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);
        Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::InTransit,
        ]);

        $this->artisan('shipments:sync-tracking')
            ->expectsOutputToContain('Checked 1 shipment(s), 1 status change(s), 1 order(s) advanced.')
            ->assertSuccessful();

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }
}
