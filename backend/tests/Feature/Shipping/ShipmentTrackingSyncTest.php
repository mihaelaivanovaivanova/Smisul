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
 * Covers ShipmentTrackingSyncService: the scheduled sync that polls every
 * non-final shipment's live carrier status and advances a Shipped order to
 * Delivered/Returned to match (see SyncShipmentTracking, scheduled hourly
 * in routes/console.php).
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

    #[Test]
    public function a_shipped_order_advances_to_delivered_when_its_shipment_tracks_as_delivered(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response($this->speedyDeliveredResponse())]);

        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);
        $shipment = Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::InTransit,
        ]);

        $result = app(ShipmentTrackingSyncService::class)->syncDue();

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

    #[Test]
    public function a_shipped_order_advances_to_returned_when_its_shipment_tracks_as_returned(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response($this->speedyReturnedResponse())]);

        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);
        Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::InTransit,
        ]);

        app(ShipmentTrackingSyncService::class)->syncDue();

        $this->assertSame(OrderStatus::Returned, $order->fresh()->status);
    }

    /**
     * Regression guard: the sync must never fight an order an admin (or any
     * other flow) has already moved on from Shipped - e.g. a manual
     * correction, or an order the carrier reported delivered on a previous
     * run already. The shipment's own status still updates either way.
     */
    #[Test]
    public function an_order_no_longer_sitting_at_shipped_is_left_alone_even_if_its_shipment_reports_delivered(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response($this->speedyDeliveredResponse())]);

        $order = Order::factory()->create(['status' => OrderStatus::Delivered, 'shipping_carrier' => ShippingCarrier::Speedy]);
        $shipment = Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::InTransit,
        ]);

        $result = app(ShipmentTrackingSyncService::class)->syncDue();

        $this->assertSame(0, $result['orders_updated']);
        $this->assertSame(ShipmentStatus::Delivered, $shipment->fresh()->status);
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }

    #[Test]
    public function a_shipment_already_at_a_final_status_is_not_queried_again(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Delivered, 'shipping_carrier' => ShippingCarrier::Speedy]);
        Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::Delivered,
        ]);

        Http::fake(['api.speedy.bg/*' => Http::response($this->speedyDeliveredResponse())]);

        $result = app(ShipmentTrackingSyncService::class)->syncDue();

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

        $result = app(ShipmentTrackingSyncService::class)->syncDue();

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

        $result = app(ShipmentTrackingSyncService::class)->syncDue();

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
