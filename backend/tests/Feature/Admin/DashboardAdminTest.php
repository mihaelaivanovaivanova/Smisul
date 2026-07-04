<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_customer_cannot_access_the_dashboard(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }

    #[Test]
    public function a_guest_is_unauthenticated_on_the_dashboard(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
    }

    #[Test]
    public function an_administrator_sees_summary_statistics(): void
    {
        $admin = User::factory()->administrator()->create();
        Order::factory()->create(['status' => OrderStatus::Paid, 'grand_total' => 100]);
        Order::factory()->create(['status' => OrderStatus::Pending, 'grand_total' => 20]);
        User::factory()->count(2)->create();
        Product::factory()->count(3)->create();
        Inventory::factory()->create(['quantity_on_hand' => 2, 'quantity_reserved' => 0, 'low_stock_threshold' => 5]);
        Inventory::factory()->outOfStock()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.total_orders', 2);
        $response->assertJsonPath('data.total_revenue', 100);
        $response->assertJsonPath('data.low_stock_products', 1);
        $response->assertJsonPath('data.out_of_stock_products', 1);
        $response->assertJsonStructure([
            'data' => [
                'total_orders', 'orders_today', 'revenue_today', 'total_revenue',
                'total_customers', 'total_products', 'low_stock_products',
                'out_of_stock_products', 'latest_orders',
            ],
        ]);
    }

    #[Test]
    public function total_customers_excludes_administrators(): void
    {
        $admin = User::factory()->administrator()->create();
        User::factory()->count(3)->create();
        User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.total_customers', 3);
    }
}
