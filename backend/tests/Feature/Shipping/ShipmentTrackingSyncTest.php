<?php

namespace Tests\Feature\Shipping;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingCarrier;
use App\Mail\ShipmentReadyForPickupMail;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\ShipmentTrackingSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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

    /**
     * Regression fixture: a real case where Speedy logs an operation
     * *after* delivery that this app doesn't recognize - confirmed live,
     * every COD shipment gets a trailing "declined/accepted inspection"
     * (OBPD) operation once the courier hands the parcel over. Before
     * ShipmentStatus::isSticky() existed, simply trusting whichever
     * operation came last in the list meant this unmapped trailing one
     * (operationCode 999 here, a placeholder for whatever Speedy's real
     * code for it turns out to be) silently won over the real delivery
     * (-14) that preceded it, leaving the shipment stuck pre-delivery
     * forever.
     */
    private function speedyDeliveredWithUnmappedTrailingOperationResponse(): array
    {
        return [
            'parcels' => [[
                'operations' => [
                    ['operationCode' => 1, 'dateTime' => '2026-07-06T10:00:00+03:00', 'description' => null],
                    ['operationCode' => -14, 'dateTime' => '2026-07-07T10:00:00+03:00', 'description' => 'Доставка на клиент'],
                    ['operationCode' => 999, 'dateTime' => '2026-07-07T10:00:01+03:00', 'description' => 'Отказ от преглед/тестване'],
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

    /**
     * Same history as speedyReturnedResponse() above, plus a later,
     * unmapped operation - a real case: a genuinely-returned order's own
     * confirmation step ("Предаване обратно на подател" - "handed back to
     * sender") is itself unmapped. operationCode 999 stands in for
     * whichever real code Speedy actually uses for it (still unconfirmed,
     * see SpeedyShippingProvider::mapStatus()'s own docblock). Used to
     * prove the opposite of what its name once implied: Returned IS
     * sticky, so this later unmapped operation must NOT un-stick it - see
     * ShipmentStatus::isSticky()'s own docblock for why a non-sticky
     * version of Returned was tried and reverted.
     */
    private function speedyReturnedThenUnmappedFollowUpResponse(): array
    {
        return [
            'parcels' => [[
                'operations' => [
                    ['operationCode' => 1, 'dateTime' => '2026-07-06T10:00:00+03:00', 'description' => null],
                    ['operationCode' => 38, 'dateTime' => '2026-07-08T10:00:00+03:00', 'description' => 'Връщане в офис'],
                    ['operationCode' => 999, 'dateTime' => '2026-07-09T10:00:00+03:00', 'description' => 'Насочена към офис'],
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

        $this->assertSame([
            'checked' => 1,
            'updated' => 1,
            'orders_updated' => 1,
            'failed' => 0,
            'pickup_reminders_sent' => 0,
            'pickup_reminders_failed' => 0,
        ], $result);
        $this->assertSame(ShipmentStatus::Delivered, $shipment->fresh()->status);
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => OrderStatus::Delivered->value,
            'previous_status' => OrderStatus::Shipped->value,
            'changed_by_user_id' => null,
        ]);
    }

    /**
     * Regression test for the real production bug: an unmapped operation
     * logged *after* delivery (see speedyDeliveredWithUnmappedTrailingOperationResponse())
     * must not hide the real delivery that preceded it.
     */
    #[Test]
    public function a_shipped_order_still_advances_to_delivered_despite_an_unmapped_trailing_operation(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response($this->speedyDeliveredWithUnmappedTrailingOperationResponse())]);

        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);
        $shipment = Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::InTransit,
        ]);

        app(ShipmentTrackingSyncService::class)->sync();

        $this->assertSame(ShipmentStatus::Delivered, $shipment->fresh()->status);
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
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
     * Regression test for a real production case: Speedy's "Връщане в
     * офис" ("returned to office") tracking event fired mid-journey as a
     * retry/redirect step, not a true final return - the carrier kept
     * re-attempting delivery for days afterward and eventually delivered
     * it. Before this fix, a Returned shipment/order was excluded from
     * every future sync (ShipmentStatus::isFinal() + the order-status
     * filter), so the real delivery that followed was never seen and the
     * order stayed wrongly marked Returned forever. Now sync() keeps
     * polling a Returned shipment and self-corrects the order to
     * Delivered once the carrier confirms it (see OrderStatusService::
     * TRANSITIONS' Returned entry).
     */
    #[Test]
    public function an_order_wrongly_marked_returned_self_corrects_to_delivered_on_a_later_sync(): void
    {
        // Both responses queued upfront via Http::sequence() rather than
        // calling Http::fake() again between the two sync() calls below -
        // re-registering a fake for the same URL pattern isn't guaranteed
        // to take priority over the first one, a sequence is deterministic.
        Http::fake([
            'api.speedy.bg/*' => Http::sequence()
                ->push($this->speedyReturnedResponse())
                ->push($this->speedyDeliveredResponse()),
        ]);

        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);
        $shipment = Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::InTransit,
        ]);

        app(ShipmentTrackingSyncService::class)->sync();

        $this->assertSame(OrderStatus::Returned, $order->fresh()->status);
        $this->assertSame(ShipmentStatus::Returned, $shipment->fresh()->status);

        // The carrier redirected the parcel and eventually delivered it -
        // a later sync should see this new history and self-correct.
        $result = app(ShipmentTrackingSyncService::class)->sync();

        $this->assertSame(1, $result['checked']);
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertSame(ShipmentStatus::Delivered, $shipment->fresh()->status);
    }

    /**
     * Regression test for a real production case: a genuinely-returned
     * order's own unmapped trailing confirmation operation ("Предаване
     * обратно на подател") must NOT un-stick the correct Returned verdict
     * - it did, briefly, under a since-reverted "Returned isn't sticky"
     * design (see ShipmentStatus::isSticky()'s own docblock). The order
     * must stay Returned here, not flip to Shipped or anything else.
     */
    #[Test]
    public function a_genuinely_returned_order_stays_returned_despite_an_unmapped_trailing_operation(): void
    {
        Http::fake([
            'api.speedy.bg/*' => Http::sequence()
                ->push($this->speedyReturnedResponse())
                ->push($this->speedyReturnedThenUnmappedFollowUpResponse()),
        ]);

        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);
        $shipment = Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::InTransit,
        ]);

        app(ShipmentTrackingSyncService::class)->sync();

        $this->assertSame(OrderStatus::Returned, $order->fresh()->status);

        $result = app(ShipmentTrackingSyncService::class)->sync();

        // The computed current status is still Returned (the sticky event
        // wins over the new unmapped one), so this doesn't even register
        // as a status change - the order correctly never moves.
        $this->assertSame(1, $result['checked']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(OrderStatus::Returned, $order->fresh()->status);
        $this->assertSame(ShipmentStatus::Returned, $shipment->fresh()->status);
    }

    /**
     * A shipment/order that really, permanently never gets collected keeps
     * being polled (no way to distinguish "still retrying" from "truly
     * done" without a real Speedy signal we don't have yet - see
     * SpeedyShippingProvider::mapStatus()'s own docblock) - confirms this
     * doesn't error or double-fire the transition when the carrier just
     * keeps reporting the same Returned state.
     */
    #[Test]
    public function a_still_returned_shipment_is_polled_again_without_error_or_duplicate_transition(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response($this->speedyReturnedResponse())]);

        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);
        Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::InTransit,
        ]);

        app(ShipmentTrackingSyncService::class)->sync();
        $result = app(ShipmentTrackingSyncService::class)->sync();

        $this->assertSame(1, $result['checked']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['orders_updated']);
        $this->assertSame(OrderStatus::Returned, $order->fresh()->status);
        $this->assertSame(1, $order->statusHistories()->where('status', OrderStatus::Returned)->count());
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

    /**
     * BOX NOW's 'final-destination' state means the parcel reached its
     * destination locker and is waiting for the customer - this is the
     * trigger for the whole pickup-reminder feature (see
     * ShipmentPickupReminderService), so confirms both that
     * ready_for_pickup_at gets set the moment this is first observed, and
     * that the immediate (stage 1) reminder email goes out in the same
     * sync run.
     */
    #[Test]
    public function a_box_now_shipment_reaching_final_destination_is_marked_ready_for_pickup_and_sends_the_first_reminder(): void
    {
        Mail::fake();

        Http::fake([
            'api-production.boxnow.bg/api/v1/auth-sessions' => Http::response([
                'access_token' => 'test-token', 'token_type' => 'Bearer', 'expires_in' => 3600,
            ]),
            'api-production.boxnow.bg/api/v1/parcels*' => Http::response([
                'data' => [[
                    'state' => 'final-destination',
                    'events' => [
                        ['type' => 'in-transit', 'locationDisplayName' => null, 'createTime' => '2026-07-06T10:00:00+03:00'],
                        ['type' => 'final-destination', 'locationDisplayName' => null, 'createTime' => '2026-07-07T10:00:00+03:00'],
                    ],
                ]],
            ]),
        ]);

        $order = Order::factory()->create([
            'status' => OrderStatus::Shipped,
            'shipping_carrier' => ShippingCarrier::BoxNow,
            'customer_email' => 'ivan@example.com',
        ]);
        $shipment = Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::BoxNow,
            'status' => ShipmentStatus::InTransit,
            'office_name' => 'BOX NOW - Mall Sofia',
        ]);

        $result = app(ShipmentTrackingSyncService::class)->sync();

        $this->assertSame(1, $result['pickup_reminders_sent']);
        $shipment->refresh();
        $this->assertSame(ShipmentStatus::ReadyForPickup, $shipment->status);
        $this->assertNotNull($shipment->ready_for_pickup_at);
        $this->assertNotNull($shipment->pickup_reminder_sent_at);
        $this->assertNull($shipment->pickup_reminder_2_sent_at);
        $this->assertNull($shipment->pickup_reminder_3_sent_at);
        // Order stays Shipped - ReadyForPickup is a shipment-level signal
        // only, not a new order lifecycle stage (see advanceOrderStatus()'s
        // match, which only ever acts on Delivered/Returned).
        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
        Mail::assertSent(
            ShipmentReadyForPickupMail::class,
            fn (ShipmentReadyForPickupMail $mail) => $mail->hasTo('ivan@example.com') && $mail->stage === 1,
        );
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
            ->expectsOutputToContain('Checked 1 shipment(s), 1 status change(s), 1 order(s) advanced, 0 pickup reminder(s) sent.')
            ->assertSuccessful();

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }
}
