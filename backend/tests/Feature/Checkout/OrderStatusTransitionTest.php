<?php

namespace Tests\Feature\Checkout;

use App\Enums\OrderStatus;
use App\Exceptions\Order\InvalidOrderStatusTransitionException;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_new_orders_first_history_entry_is_recorded_automatically_at_placement(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        app(OrderStatusService::class)->recordInitial($order);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => OrderStatus::Pending->value,
            'previous_status' => null,
            'changed_by_user_id' => null,
        ]);
    }

    #[Test]
    public function a_valid_transition_updates_the_order_and_records_history(): void
    {
        $admin = User::factory()->administrator()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $updated = app(OrderStatusService::class)->transitionTo($order, OrderStatus::AwaitingPayment, $admin, 'Redirected to gateway');

        $this->assertSame(OrderStatus::AwaitingPayment, $updated->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => OrderStatus::AwaitingPayment->value,
            'previous_status' => OrderStatus::Pending->value,
            'changed_by_user_id' => $admin->id,
            'note' => 'Redirected to gateway',
        ]);
    }

    #[Test]
    public function an_invalid_transition_is_rejected_and_leaves_the_order_unchanged(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Delivered]);

        $this->expectException(InvalidOrderStatusTransitionException::class);

        try {
            app(OrderStatusService::class)->transitionTo($order, OrderStatus::Pending, null);
        } finally {
            $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
            $this->assertDatabaseCount('order_status_histories', 0);
        }
    }

    #[Test]
    public function terminal_statuses_have_no_further_transitions_except_refund(): void
    {
        $service = app(OrderStatusService::class);

        $this->assertSame([], $service->allowedTransitions(OrderStatus::Refunded));
        $this->assertSame([OrderStatus::Refunded], $service->allowedTransitions(OrderStatus::Cancelled));
    }

    #[Test]
    public function the_full_happy_path_workflow_is_walkable_end_to_end(): void
    {
        $service = app(OrderStatusService::class);
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        foreach ([
            OrderStatus::Paid,
            OrderStatus::Processing,
            OrderStatus::Packed,
            OrderStatus::Shipped,
            OrderStatus::Delivered,
            OrderStatus::Completed,
        ] as $status) {
            $order = $service->transitionTo($order, $status, null);
        }

        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertDatabaseCount('order_status_histories', 6);
    }
}
