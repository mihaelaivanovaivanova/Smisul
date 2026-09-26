<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Confirms 2026_09_27_000000_revert_completed_orders_to_delivered.php does
 * exactly what it's supposed to and nothing more - in particular, that it
 * does NOT resend the customer's delivered/invoice emails, since those are
 * only ever triggered by OrderStatusChanged (dispatched exclusively from
 * OrderStatusService::transitionTo()), and this migration deliberately
 * writes via DB::table() instead of going through that method.
 */
class RevertCompletedOrdersToDeliveredMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_09_27_000000_revert_completed_orders_to_delivered.php');
        $migration->up();
    }

    #[Test]
    public function it_moves_completed_orders_back_to_delivered_without_sending_any_email(): void
    {
        Mail::fake();

        $order = Order::factory()->create(['status' => OrderStatus::Completed]);

        $this->runMigration();

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => OrderStatus::Delivered->value,
            'previous_status' => OrderStatus::Completed->value,
            'changed_by_user_id' => null,
        ]);
        Mail::assertNothingSent();
    }

    #[Test]
    public function it_leaves_orders_at_other_statuses_untouched(): void
    {
        $delivered = Order::factory()->create(['status' => OrderStatus::Delivered]);
        $paid = Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->runMigration();

        $this->assertSame(OrderStatus::Delivered, $delivered->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $paid->fresh()->status);
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    #[Test]
    public function running_it_with_no_completed_orders_does_nothing(): void
    {
        Order::factory()->create(['status' => OrderStatus::Delivered]);

        $this->runMigration();

        $this->assertDatabaseCount('order_status_histories', 0);
    }
}
