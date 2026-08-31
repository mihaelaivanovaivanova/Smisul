<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    /**
     * A plain browser navigation (not an XHR) to a protected admin
     * endpoint - e.g. the label-download link, opened with a stale/expired
     * session - sends Accept: text/html, so Laravel's auth middleware
     * tries to *redirect* a guest rather than return a bare 401. This app
     * has no server-rendered login page, so that redirect must go to the
     * real frontend login route (see bootstrap/app.php's
     * redirectGuestsTo()) - not Laravel's route('login') default, which
     * resolves to the POST-only /api/v1/auth/login and 405s on the GET a
     * browser sends. Reproduced directly: this was the actual failure
     * behind a broken "Download label" click.
     */
    #[Test]
    public function a_guest_browser_navigation_to_an_admin_endpoint_redirects_to_the_frontend_login_page(): void
    {
        $order = Order::factory()->create();

        $this->get("/api/v1/admin/orders/{$order->id}/shipment/label", ['Accept' => 'text/html'])
            ->assertRedirect(rtrim((string) config('app.frontend_url'), '/').'/login');
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

    #[Test]
    public function an_administrator_can_manually_create_a_shipment_for_an_order_without_one(): void
    {
        Http::fake([
            'api-production.boxnow.bg/api/v1/auth-sessions' => Http::response(['access_token' => 'test-token', 'token_type' => 'Bearer', 'expires_in' => 3600]),
            'api-production.boxnow.bg/api/v1/delivery-requests' => Http::response([
                'id' => 'DR-MANUAL-1',
                'parcels' => [['id' => 'BN-MANUAL-1']],
            ]),
        ]);

        $admin = User::factory()->administrator()->create();
        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::BoxNow,
            'shipping_delivery_type' => ShippingDeliveryType::Locker,
            'shipping_office_id' => 'locker-9',
        ]);
        OrderItem::factory()->for($order)->create();

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/orders/{$order->id}/shipment");

        $response->assertOk();
        $response->assertJsonPath('data.shipment.tracking_number', 'BN-MANUAL-1');
    }

    #[Test]
    public function creating_a_shipment_for_an_order_that_already_has_one_is_rejected(): void
    {
        $admin = User::factory()->administrator()->create();
        $order = Order::factory()->create(['shipping_carrier' => ShippingCarrier::BoxNow]);
        Shipment::factory()->for($order)->created()->create(['carrier' => ShippingCarrier::BoxNow]);

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/orders/{$order->id}/shipment");

        $response->assertStatus(422);
    }

    #[Test]
    public function an_administrator_can_download_a_box_now_shipment_label(): void
    {
        Http::fake([
            'api-production.boxnow.bg/api/v1/auth-sessions' => Http::response(['access_token' => 'test-token', 'token_type' => 'Bearer', 'expires_in' => 3600]),
            'api-production.boxnow.bg/api/v1/parcels/BN-LABEL-1/label.pdf' => Http::response('%PDF-1.4 fake label bytes', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $admin = User::factory()->administrator()->create();
        $order = Order::factory()->create(['shipping_carrier' => ShippingCarrier::BoxNow]);
        Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::BoxNow,
            'tracking_number' => 'BN-LABEL-1',
        ]);

        $response = $this->actingAs($admin)->get("/api/v1/admin/orders/{$order->id}/shipment/label");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('fake label bytes', $response->getContent());
    }

    #[Test]
    public function downloading_a_label_for_an_order_with_no_shipment_is_not_found(): void
    {
        $admin = User::factory()->administrator()->create();
        $order = Order::factory()->create();

        $this->actingAs($admin)->get("/api/v1/admin/orders/{$order->id}/shipment/label")
            ->assertNotFound();
    }

    #[Test]
    public function downloading_a_label_for_an_unimplemented_carrier_returns_a_clear_error(): void
    {
        $admin = User::factory()->administrator()->create();
        $order = Order::factory()->create(['shipping_carrier' => ShippingCarrier::Speedy]);
        Shipment::factory()->for($order)->created()->create(['carrier' => ShippingCarrier::Speedy]);

        $response = $this->actingAs($admin)->get("/api/v1/admin/orders/{$order->id}/shipment/label");

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => "speedy fetchLabel request failed: not implemented - Speedy's label/printLabel endpoint has not been confirmed against their real API yet."]);
    }
}
