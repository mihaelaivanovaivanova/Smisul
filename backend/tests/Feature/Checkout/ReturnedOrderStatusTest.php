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
     * Shipped and Delivered are allowed too, not just Refunded - a
     * self-correction path for ShipmentTrackingSyncService: Speedy's own
     * "Връщане в офис" ("returned to office") tracking event can fire
     * mid-journey, as a retry/redirect step, not a true final return
     * (confirmed live - the carrier kept re-attempting delivery for days
     * after it fired). Back to Shipped is the normal case (the carrier is
     * still actively handling it - ordinary Shipped -> Delivered/Returned
     * takes it from there next); straight to Delivered only happens if a
     * single sync run observes it already fully delivered with no Shipped
     * re-observed in between. See OrderStatusService::TRANSITIONS' own
     * comment on Returned, and the two tests below.
     */
    #[Test]
    public function a_returned_order_can_move_on_to_shipped_delivered_or_refunded(): void
    {
        $service = app(OrderStatusService::class);

        $this->assertSame(
            [OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Refunded],
            $service->allowedTransitions(OrderStatus::Returned),
        );
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
    public function a_returned_order_can_self_correct_back_to_shipped(): void
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
    public function a_returned_order_can_self_correct_straight_to_delivered(): void
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

    #[Test]
    public function a_returned_order_cannot_be_packed_again(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Returned]);

        $this->expectException(InvalidOrderStatusTransitionException::class);

        app(OrderStatusService::class)->transitionTo($order, OrderStatus::Packed, null);
    }
}
