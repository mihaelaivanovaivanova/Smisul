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

    /**
     * A real case: a failed delivery attempt (operationCode 44) followed
     * later by the real Returned event (38) - confirms Failed doesn't
     * permanently stick over a later Returned/Delivered the way it
     * briefly did during a reverted intermediate design (see
     * ShipmentTrackingSyncService's own docblock).
     */
    private function speedyFailedThenReturnedResponse(): array
    {
        return [
            'parcels' => [[
                'operations' => [
                    ['operationCode' => 1, 'dateTime' => '2026-07-06T10:00:00+03:00', 'description' => null],
                    ['operationCode' => 44, 'dateTime' => '2026-07-07T10:00:00+03:00', 'description' => 'Неуспешна доставка'],
                    ['operationCode' => 38, 'dateTime' => '2026-07-10T10:00:00+03:00', 'description' => 'Връщане към подателя'],
                ],
            ]],
        ];
    }

    /**
     * operationCode 38 is Speedy's own return-family code, but only counts
     * as a genuine "returned to sender" when the description actually
     * names подателя (the sender) - see SpeedyShippingProvider::
     * mapStatus()'s own docblock. "Връщане към подателя" is the real text
     * confirmed live on real orders (KFAXTA/VJYMEJ).
     */
    private function speedyReturnedResponse(): array
    {
        return [
            'parcels' => [[
                'operations' => [
                    ['operationCode' => 1, 'dateTime' => '2026-07-06T10:00:00+03:00', 'description' => null],
                    ['operationCode' => 38, 'dateTime' => '2026-07-08T10:00:00+03:00', 'description' => 'Връщане към подателя'],
                ],
            ]],
        ];
    }

    /**
     * Same history as speedyReturnedResponse() above, plus a later,
     * unmapped operation - a real case: a genuinely-returned order's own
     * confirmation step ("Предаване обратно на подател" - "handed back to
     * sender") is itself unmapped (operationCode 999 stands in for
     * whichever real code Speedy actually uses for it). Used to prove the
     * opposite of what its name once implied: Returned IS sticky, so this
     * later unmapped operation must NOT un-stick it - see ShipmentStatus::
     * isSticky()'s own docblock for why a non-sticky version of Returned
     * was tried and reverted.
     */
    private function speedyReturnedThenUnmappedFollowUpResponse(): array
    {
        return [
            'parcels' => [[
                'operations' => [
                    ['operationCode' => 1, 'dateTime' => '2026-07-06T10:00:00+03:00', 'description' => null],
                    ['operationCode' => 38, 'dateTime' => '2026-07-08T10:00:00+03:00', 'description' => 'Връщане към подателя'],
                    ['operationCode' => 999, 'dateTime' => '2026-07-09T10:00:00+03:00', 'description' => 'Предаване обратно на подател'],
                ],
            ]],
        ];
    }

    /**
     * Same real events as speedyReturnedThenUnmappedFollowUpResponse()
     * above, but deliberately out of chronological order in the array -
     * confirmed live that Speedy's track endpoint doesn't reliably return
     * operations in the same order across repeated calls for the same
     * parcel (real orders KWAKHR/CYYCSE/SQ369U flipped between Returned
     * and Shipped on every alternating sync run, including consecutive
     * CLI invocations - fresh PHP processes each time, ruling out
     * OPcache). mostAdvancedStatus() must resolve this the same way
     * regardless of array order - see SpeedyShippingProvider::track()'s
     * own docblock on sorting by occurredAt rather than trusting the API's
     * array order.
     */
    private function speedyReturnedWithOutOfOrderOperationsResponse(): array
    {
        return [
            'parcels' => [[
                'operations' => [
                    ['operationCode' => 999, 'dateTime' => '2026-07-09T10:00:00+03:00', 'description' => 'Предаване обратно на подател'],
                    ['operationCode' => 1, 'dateTime' => '2026-07-06T10:00:00+03:00', 'description' => null],
                    ['operationCode' => 38, 'dateTime' => '2026-07-08T10:00:00+03:00', 'description' => 'Връщане към подателя'],
                ],
            ]],
        ];
    }

    /**
     * Regression fixture for the real SQ369U case: operationCode 38 with
     * description "Връщане в офис" (redirected back to a local office
     * mid-journey, not a final return) must NOT be read as Returned -
     * confirmed live, the real parcel kept moving through "Уточняване на
     * доставка" for days afterward rather than ever coming back to us. See
     * SpeedyShippingProvider::mapStatus()'s own docblock.
     */
    private function speedyOfficeRedirectResponse(): array
    {
        return [
            'parcels' => [[
                'operations' => [
                    ['operationCode' => 1, 'dateTime' => '2026-07-06T10:00:00+03:00', 'description' => null],
                    ['operationCode' => 1134, 'dateTime' => '2026-07-07T10:00:00+03:00', 'description' => 'Изпратено известие за пратка в офис/автомат'],
                    ['operationCode' => 38, 'dateTime' => '2026-07-10T10:00:00+03:00', 'description' => 'Връщане в офис'],
                    ['operationCode' => 136, 'dateTime' => '2026-07-11T10:00:00+03:00', 'description' => 'Уточняване на доставка'],
                ],
            ]],
        ];
    }

    /**
     * operationCode 1134 is ShipmentStatus::ReadyForPickup's real Speedy
     * code - confirmed live (SQ369U), firing the day after arrival at the
     * office.
     */
    private function speedyReadyForPickupResponse(): array
    {
        return [
            'parcels' => [[
                'operations' => [
                    ['operationCode' => 1, 'dateTime' => '2026-07-06T10:00:00+03:00', 'description' => null],
                    ['operationCode' => 1134, 'dateTime' => '2026-07-07T10:00:00+03:00', 'description' => 'Изпратено известие за пратка в офис/автомат'],
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
     * Regression test for the real production bug that caused order status
     * to visibly flip back and forth on every single sync run (KWAKHR,
     * CYYCSE, SQ369U and others, confirmed live even across independent
     * CLI invocations): the computed status must be the same regardless of
     * what order Speedy's API happens to return operations in. Same real
     * event set as a_genuinely_returned_order_stays_returned_despite_an_
     * unmapped_trailing_operation above, fed out of order every time -
     * must resolve to Returned every single call, not alternate.
     */
    #[Test]
    public function the_computed_status_does_not_depend_on_the_carriers_own_operation_order(): void
    {
        Http::fake([
            'api.speedy.bg/*' => Http::response($this->speedyReturnedWithOutOfOrderOperationsResponse()),
        ]);

        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);
        $shipment = Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::InTransit,
        ]);

        for ($i = 0; $i < 5; $i++) {
            app(ShipmentTrackingSyncService::class)->sync();

            $this->assertSame(ShipmentStatus::Returned, $shipment->fresh()->status);
            $this->assertSame(OrderStatus::Returned, $order->fresh()->status);
        }
    }

    /**
     * A genuinely-returned shipment/order (description names the sender -
     * see SpeedyShippingProvider::mapStatus()'s own docblock) keeps being
     * polled anyway - confirms this doesn't error or double-fire the
     * transition when the carrier just keeps reporting the same Returned
     * state.
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
     * Regression test for the real production case that started this
     * whole investigation (order SQ369U): Speedy's "Връщане в офис"
     * ("returned to office") shares operationCode 38 with a genuine final
     * return, but is just a mid-journey redirect - the carrier kept
     * re-attempting delivery for days afterward. Must NOT be read as
     * Returned - the order has to stay Shipped so the sync keeps checking
     * it the normal way, not via Returned's special still-polling carve-
     * out. See SpeedyShippingProvider::mapStatus()'s own docblock for the
     * description-based fix this confirms.
     */
    #[Test]
    public function a_speedy_office_redirect_does_not_mark_the_shipment_returned(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response($this->speedyOfficeRedirectResponse())]);

        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);
        $shipment = Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::ReadyForPickup,
        ]);

        app(ShipmentTrackingSyncService::class)->sync();

        $this->assertNotSame(ShipmentStatus::Returned, $shipment->fresh()->status);
        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
    }

    /**
     * Regression test for the exact stuck state SQ369U was found in: an
     * order/shipment pair already persisted at Returned from before this
     * fix was deployed (the old code had no way to tell "Връщане в офис"
     * apart from a genuine return) must self-correct back to Shipped once
     * a sync against the fixed mapStatus() shows the shipment was never
     * really returned - see OrderStatusService::TRANSITIONS' Returned
     * entry and ShipmentTrackingSyncService::advanceOrderStatus()'s own
     * docblock for why this is now safe to allow.
     */
    #[Test]
    public function an_order_wrongly_stuck_at_returned_from_before_this_fix_self_corrects_back_to_shipped(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response($this->speedyOfficeRedirectResponse())]);

        $order = Order::factory()->create(['status' => OrderStatus::Returned, 'shipping_carrier' => ShippingCarrier::Speedy]);
        $shipment = Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::Returned,
        ]);

        app(ShipmentTrackingSyncService::class)->sync();

        $this->assertNotSame(ShipmentStatus::Returned, $shipment->fresh()->status);
        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => OrderStatus::Shipped->value,
            'previous_status' => OrderStatus::Returned->value,
        ]);
    }

    /**
     * operationCode 1134 is ShipmentStatus::ReadyForPickup's real Speedy
     * code - confirmed live (SQ369U). Mirrors the equivalent BOX NOW test
     * below (a_box_now_shipment_reaching_final_destination_...) - setting
     * ready_for_pickup_at is carrier-agnostic once the status lands on
     * ReadyForPickup. No email is sent here - sync() doesn't send mail at
     * all anymore (see ShipmentPickupNotificationService for the separate
     * manual "Send pickup notification" button this replaced).
     */
    #[Test]
    public function a_speedy_shipment_reaching_the_office_is_marked_ready_for_pickup(): void
    {
        Mail::fake();
        Http::fake(['api.speedy.bg/*' => Http::response($this->speedyReadyForPickupResponse())]);

        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);
        $shipment = Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::InTransit,
        ]);

        app(ShipmentTrackingSyncService::class)->sync();

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::ReadyForPickup, $shipment->status);
        $this->assertNotNull($shipment->ready_for_pickup_at);
        Mail::assertNothingSent();
    }

    /**
     * Regression test for a real production case: Failed must not stop
     * polling the way Delivered/Cancelled do - a failed delivery attempt
     * is often followed by a real Returned (or Delivered) outcome, and a
     * shipment stuck recording Failed would otherwise never be checked
     * again (same class of bug the Returned fix above addresses).
     */
    #[Test]
    public function a_failed_shipment_keeps_being_polled_and_self_corrects_to_returned(): void
    {
        Http::fake([
            'api.speedy.bg/*' => Http::sequence()
                ->push($this->speedyFailedThenReturnedResponse())
                ->push($this->speedyFailedThenReturnedResponse()),
        ]);

        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);
        $shipment = Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::InTransit,
        ]);

        app(ShipmentTrackingSyncService::class)->sync();

        $this->assertSame(ShipmentStatus::Returned, $shipment->fresh()->status);
        $this->assertSame(OrderStatus::Returned, $order->fresh()->status);

        // A shipment that genuinely did fail first, then a day later
        // actually failed again (same raw response re-fetched, nothing
        // new) must still be polled on the next run, not silently
        // excluded the way Delivered/Cancelled are.
        $result = app(ShipmentTrackingSyncService::class)->sync();
        $this->assertSame(1, $result['checked']);
    }

    /**
     * Regression test: a shipment whose status is already correct (e.g.
     * corrected by a direct track() call outside the normal sync loop,
     * like a one-off tinker fix) must still bring its order in line on
     * the next sync run, even though the shipment's own status doesn't
     * change *during* that run. Before this fix, advanceOrderStatus() was
     * only ever attempted when sync() detected a fresh change in the same
     * run, leaving an already-correct-but-desynced shipment/order pair
     * stuck forever.
     */
    #[Test]
    public function a_shipment_already_at_its_correct_status_still_advances_a_desynced_order(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response($this->speedyReturnedResponse())]);

        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);
        $shipment = Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            // Already Returned - as if a one-off direct track() call had
            // already corrected the shipment, but nothing had touched the
            // order yet.
            'status' => ShipmentStatus::Returned,
        ]);

        $result = app(ShipmentTrackingSyncService::class)->sync();

        // The shipment's own status doesn't change this run (it was
        // already Returned), but the order still gets brought in line.
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['orders_updated']);
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

    /**
     * BOX NOW's 'final-destination' state means the parcel reached its
     * destination locker and is waiting for the customer - confirms
     * ready_for_pickup_at gets set the moment this is first observed. No
     * email is sent here - sync() doesn't send mail at all anymore (see
     * ShipmentPickupNotificationService for the separate manual "Send
     * pickup notification" button this replaced, which is Speedy-only by
     * request - BOX NOW gets no equivalent).
     */
    #[Test]
    public function a_box_now_shipment_reaching_final_destination_is_marked_ready_for_pickup(): void
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

        app(ShipmentTrackingSyncService::class)->sync();

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::ReadyForPickup, $shipment->status);
        $this->assertNotNull($shipment->ready_for_pickup_at);
        // Order stays Shipped - ReadyForPickup is a shipment-level signal
        // only, not a new order lifecycle stage (see advanceOrderStatus()'s
        // match, which only ever acts on Delivered/Returned).
        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
        Mail::assertNothingSent();
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
