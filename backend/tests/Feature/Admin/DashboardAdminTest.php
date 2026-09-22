<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Favorite;
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
                'out_of_stock_products', 'total_favorites',
            ],
        ]);
    }

    #[Test]
    public function the_dashboard_reports_the_total_number_of_favorites(): void
    {
        $admin = User::factory()->administrator()->create();
        Favorite::factory()->count(4)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.total_favorites', 4);
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

    /**
     * Internal/test accounts (see OrderService::TEST_CUSTOMER_EMAILS) place
     * real orders for onboarding/staging real carrier requests - never
     * actual customers, so they shouldn't skew what the dashboard reports
     * as real business activity. Matched case-insensitively since the list
     * itself is lowercase but real-world input rarely is.
     */
    #[Test]
    public function test_customer_accounts_and_their_orders_are_excluded_from_every_summary_figure(): void
    {
        $admin = User::factory()->administrator()->create();
        $testCustomer = User::factory()->create(['email' => 'Test@Gmail.com']);
        Order::factory()->create(['status' => OrderStatus::Paid, 'grand_total' => 500, 'customer_email' => 'VLADOFILCHEV@GMAIL.COM']);
        Order::factory()->forUser($testCustomer)->create(['status' => OrderStatus::Paid, 'grand_total' => 300, 'customer_email' => 'test@gmail.com']);
        Order::factory()->create(['status' => OrderStatus::Paid, 'grand_total' => 50, 'customer_email' => 'real-customer@example.com']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.total_orders', 1);
        $response->assertJsonPath('data.orders_today', 1);
        $response->assertJsonPath('data.total_revenue', 50);
        $response->assertJsonPath('data.revenue_today', 50);
        $response->assertJsonPath('data.total_customers', 0);
    }
}
