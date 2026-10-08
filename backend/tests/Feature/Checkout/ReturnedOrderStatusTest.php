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

    #[Test]
    public function a_returned_order_can_only_move_on_to_refunded(): void
    {
        $service = app(OrderStatusService::class);

        $this->assertSame([OrderStatus::Refunded], $service->allowedTransitions(OrderStatus::Returned));
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
    public function a_returned_order_cannot_be_delivered_afterwards(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Returned]);

        $this->expectException(InvalidOrderStatusTransitionException::class);

        app(OrderStatusService::class)->transitionTo($order, OrderStatus::Delivered, null);
    }
}
