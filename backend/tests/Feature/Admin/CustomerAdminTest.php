<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_customer_cannot_list_customers(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson('/api/v1/admin/customers')->assertForbidden();
    }

    #[Test]
    public function a_guest_is_unauthenticated_on_customer_endpoints(): void
    {
        $this->getJson('/api/v1/admin/customers')->assertUnauthorized();
    }

    #[Test]
    public function an_administrator_can_list_customers_excluding_administrators(): void
    {
        $admin = User::factory()->administrator()->create();
        User::factory()->count(2)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/customers');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function customers_can_be_searched_by_email(): void
    {
        $admin = User::factory()->administrator()->create();
        $target = User::factory()->create(['email' => 'findme@example.com']);
        User::factory()->create(['email' => 'someoneelse@example.com']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/customers?search=findme');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $target->id);
    }

    #[Test]
    public function an_administrator_can_view_a_customer_with_order_count(): void
    {
        $admin = User::factory()->administrator()->create();
        $customer = User::factory()->create();
        Order::factory()->forUser($customer)->count(2)->create();

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/customers/{$customer->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $customer->id);
        $response->assertJsonPath('data.orders_count', 2);
    }

    #[Test]
    public function a_customer_cannot_view_another_customers_detail_via_the_admin_endpoint(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($customer)->getJson("/api/v1/admin/customers/{$other->id}")->assertForbidden();
    }

    #[Test]
    public function order_history_for_a_customer_is_fetched_via_the_admin_orders_endpoint(): void
    {
        $admin = User::factory()->administrator()->create();
        $customer = User::factory()->create();
        Order::factory()->forUser($customer)->create();
        Order::factory()->create();

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/orders?user_id={$customer->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }
}
