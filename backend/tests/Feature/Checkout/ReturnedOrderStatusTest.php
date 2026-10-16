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
     * Delivered is allowed too, not just Refunded - lets
     * ShipmentTrackingSyncService self-correct a Returned order if a
     * later sync genuinely confirms delivery. Returned -> Shipped was
     * tried and reverted - see ShipmentStatus::isSticky()'s own docblock
     * for the real case that made it unsafe.
     */
    #[Test]
    public function a_returned_order_can_move_on_to_delivered_or_refunded(): void
    {
        $service = app(OrderStatusService::class);

        $this->assertSame([OrderStatus::Delivered, OrderStatus::Refunded], $service->allowedTransitions(OrderStatus::Returned));
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
     * Regression guard: Returned -> Shipped was allowed for a while (to
     * let a premature Returned self-correct while the carrier kept
     * redelivering) and then removed - see ShipmentStatus::isSticky()'s
     * own docblock for the real case that made it unsafe (a genuinely-
     * returned order's own unmapped trailing "handover confirmed"
     * operation is indistinguishable from that case with our current
     * Speedy operation-code mapping). Confirms it stays removed.
     */
    #[Test]
    public function a_returned_order_cannot_move_back_to_shipped(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Returned]);

        $this->expectException(InvalidOrderStatusTransitionException::class);

        app(OrderStatusService::class)->transitionTo($order, OrderStatus::Shipped, null);
    }

    #[Test]
    public function a_returned_order_cannot_be_packed_again(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Returned]);

        $this->expectException(InvalidOrderStatusTransitionException::class);

        app(OrderStatusService::class)->transitionTo($order, OrderStatus::Packed, null);
    }
}
