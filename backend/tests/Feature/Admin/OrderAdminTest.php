<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_customer_cannot_access_admin_order_endpoints(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson('/api/v1/admin/orders')->assertForbidden();
    }

    #[Test]
    public function a_guest_is_unauthenticated_on_admin_order_endpoints(): void
    {
        $this->getJson('/api/v1/admin/orders')->assertUnauthorized();
    }

    #[Test]
    public function an_administrator_can_list_all_orders_regardless_of_owner(): void
    {
        $admin = User::factory()->administrator()->create();
        Order::factory()->count(2)->create();
        Order::factory()->forUser()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/orders');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    #[Test]
    public function orders_can_be_filtered_by_status(): void
    {
        $admin = User::factory()->administrator()->create();
        Order::factory()->create(['status' => OrderStatus::Pending]);
        Order::factory()->create(['status' => OrderStatus::Delivered]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/orders?status=delivered');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'delivered');
    }

    #[Test]
    public function orders_can_be_searched_by_order_number_or_customer_email(): void
    {
        $admin = User::factory()->administrator()->create();
        $target = Order::factory()->create(['customer_email' => 'findme@example.com']);
        Order::factory()->create(['customer_email' => 'someoneelse@example.com']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/orders?search=findme');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $target->id);
    }

    #[Test]
    public function orders_can_be_sorted_by_total(): void
    {
        $admin = User::factory()->administrator()->create();
        $cheap = Order::factory()->create(['grand_total' => 10, 'subtotal' => 10]);
        $expensive = Order::factory()->create(['grand_total' => 500, 'subtotal' => 500]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/orders?sort=total_desc');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $expensive->id);
        $response->assertJsonPath('data.1.id', $cheap->id);
    }

    #[Test]
    public function an_administrator_can_view_any_order_detail(): void
    {
        $admin = User::factory()->administrator()->create();
        $order = Order::factory()->forUser()->create();

        $this->actingAs($admin)->getJson("/api/v1/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    #[Test]
    public function an_administrator_can_transition_an_orders_status(): void
    {
        $admin = User::factory()->administrator()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'awaiting_payment',
            'note' => 'Waiting on gateway redirect',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'awaiting_payment');
        $response->assertJsonPath('data.timeline.0.changed_by', $admin->fullName());
    }

    #[Test]
    public function an_invalid_status_transition_is_rejected_with_a_conflict(): void
    {
        $admin = User::factory()->administrator()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Delivered]);

        $this->actingAs($admin)->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'pending'])
            ->assertStatus(409);
    }

    #[Test]
    public function statistics_summarize_orders_by_status_and_revenue(): void
    {
        $admin = User::factory()->administrator()->create();
        Order::factory()->create(['status' => OrderStatus::Pending, 'grand_total' => 20]);
        Order::factory()->create(['status' => OrderStatus::Paid, 'grand_total' => 50]);
        Order::factory()->create(['status' => OrderStatus::Delivered, 'grand_total' => 30]);
        Order::factory()->create(['status' => OrderStatus::Cancelled, 'grand_total' => 999]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/orders/statistics');

        $response->assertOk();
        $response->assertJsonPath('data.total_orders', 4);
        $response->assertJsonPath('data.orders_by_status.pending', 1);
        $response->assertJsonPath('data.orders_by_status.cancelled', 1);
        // Cancelled excluded from revenue: 50 + 30, not +999.
        $response->assertJsonPath('data.total_revenue', 80);
    }
}
