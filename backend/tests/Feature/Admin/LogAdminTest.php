<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActionLog;
use App\Models\AuthenticationLog;
use App\Models\OrderStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\ShipmentStatusEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_customer_cannot_view_logs(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson('/api/v1/admin/logs?type=orders')->assertForbidden();
    }

    #[Test]
    public function a_guest_is_unauthenticated_on_log_endpoints(): void
    {
        $this->getJson('/api/v1/admin/logs?type=orders')->assertUnauthorized();
    }

    #[Test]
    public function an_unknown_log_type_is_rejected(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->getJson('/api/v1/admin/logs?type=bogus')->assertUnprocessable();
    }

    #[Test]
    public function order_events_are_listed(): void
    {
        $admin = User::factory()->administrator()->create();
        OrderStatusHistory::factory()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/logs?type=orders');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function payment_events_are_listed(): void
    {
        $admin = User::factory()->administrator()->create();
        PaymentTransaction::factory()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/logs?type=payments');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function shipment_events_are_listed(): void
    {
        $admin = User::factory()->administrator()->create();
        ShipmentStatusEvent::factory()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/logs?type=shipments');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function authentication_events_are_listed(): void
    {
        $admin = User::factory()->administrator()->create();
        AuthenticationLog::create(['email' => 'someone@example.com', 'event' => 'login']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/logs?type=authentication');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function admin_action_events_are_listed(): void
    {
        $admin = User::factory()->administrator()->create();
        AdminActionLog::create(['user_id' => $admin->id, 'action' => 'product.created']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/logs?type=admin_actions');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.message', 'product.created');
    }

    #[Test]
    public function a_successful_login_is_recorded_in_the_authentication_log(): void
    {
        $user = User::factory()->create(['email' => 'auditme@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'auditme@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->assertDatabaseHas('authentication_logs', [
            'user_id' => $user->id,
            'event' => 'login',
        ]);
    }

    #[Test]
    public function a_product_create_is_recorded_in_the_admin_action_log(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/categories', ['name' => 'Test Category'])->assertCreated();

        $this->assertDatabaseHas('admin_action_logs', [
            'user_id' => $admin->id,
            'action' => 'category.created',
        ]);
    }
}
