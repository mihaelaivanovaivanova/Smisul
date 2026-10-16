<?php

namespace Tests\Feature\Checkout;

use App\Enums\OrderStatus;
use App\Exceptions\Order\InvalidOrderStatusTransitionException;
use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReturnedOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_shipped_order_can_be_marked_returned_by_the_sender(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Shipped]);

        $updated = app(OrderStatusService::class)->transitionTo($order, OrderStatus::Returned, null, 'Parcel came back to the store');

        $this->assertSame(OrderStatus::Returned, $updated->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => OrderStatus::Returned->value,
            'previous_status' => OrderStatus::Shipped->value,
        ]);
    }

    /**
     * Shipped, Delivered and Refunded are all allowed, not just Refunded -
     * lets ShipmentTrackingSyncService self-correct a Returned order
     * either way: to Delivered if a later sync genuinely confirms
     * delivery, or back to Shipped if the shipment's own status moves away
     * from Returned - see ShipmentTrackingSyncService::advanceOrderStatus()'s
     * own docblock for why this is safe (the real ambiguity that made an
     * earlier version of this unsafe was fixed one layer down, in
     * SpeedyShippingProvider::mapStatus()).
     */
    #[Test]
    public function a_returned_order_can_move_on_to_shipped_delivered_or_refunded(): void
    {
        $service = app(OrderStatusService::class);

        $this->assertSame([OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Refunded], $service->allowedTransitions(OrderStatus::Returned));
    }

    #[Test]
    public function returned_is_only_reachable_from_shipped(): void
    {
        $service = app(OrderStatusService::class);

        foreach ([OrderStatus::Pending, OrderStatus::Paid, OrderStatus::Confirmed, OrderStatus::Packed, OrderStatus::Delivered] as $from) {
            $this->assertNotContains(OrderStatus::Returned, $service->allowedTransitions($from), "Returned must not be reachable from {$from->value}.");
        }
    }

    #[Test]
    public function a_returned_order_can_self_correct_to_delivered(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Returned]);

        $updated = app(OrderStatusService::class)->transitionTo($order, OrderStatus::Delivered, null, 'Updated from speedy tracking sync: Доставена');

        $this->assertSame(OrderStatus::Delivered, $updated->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => OrderStatus::Delivered->value,
            'previous_status' => OrderStatus::Returned->value,
        ]);
    }

    /**
     * Confirms the Returned -> Shipped self-correction path itself (see
     * ShipmentTrackingSyncService::advanceOrderStatus()'s own docblock for
     * when the sync actually triggers this - this test only covers that
     * OrderStatusService allows the transition). The end-to-end regression
     * guard for the real case that made an earlier version of this unsafe
     * (a genuinely-returned order's own unmapped trailing confirmation
     * operation) lives in ShipmentTrackingSyncTest::
     * a_genuinely_returned_order_stays_returned_despite_an_unmapped_trailing_operation().
     */
    #[Test]
    public function a_returned_order_can_self_correct_to_shipped(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Returned]);

        $updated = app(OrderStatusService::class)->transitionTo($order, OrderStatus::Shipped, null, 'Updated from speedy tracking sync: В транзит');

        $this->assertSame(OrderStatus::Shipped, $updated->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => OrderStatus::Shipped->value,
            'previous_status' => OrderStatus::Returned->value,
        ]);
    }

    #[Test]
    public function a_returned_order_cannot_be_packed_again(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Returned]);

        $this->expectException(InvalidOrderStatusTransitionException::class);

        app(OrderStatusService::class)->transitionTo($order, OrderStatus::Packed, null);
    }
}
