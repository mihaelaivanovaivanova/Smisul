<?php

namespace Tests\Feature\Checkout;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers OrderController::reviewIdentity - the endpoint
 * OrderThirtyDayReminderMail's "Остави ревю" link points the review wizard
 * at so it can skip asking for an email/display name it already knows (see
 * that mailable's reviewUrl() docblock). Same signed-link-only
 * authorization as reviews.confirm (see ReviewSubmissionTest), deliberately
 * not guest_access_token - this must work for a registered customer's
 * order too, which never gets one.
 */
class OrderReviewIdentityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_valid_signed_link_resolves_the_orders_customer_identity(): void
    {
        $order = Order::factory()->create([
            'customer_email' => 'ivan@example.com',
            'customer_first_name' => 'Ivan',
        ]);

        $signedUrl = URL::temporarySignedRoute('orders.review-identity', now()->addDays(30), ['order' => $order->id]);

        $response = $this->getJson($signedUrl);

        $response->assertOk();
        $response->assertJsonPath('data.email', 'ivan@example.com');
        $response->assertJsonPath('data.display_name', 'Ivan');
    }

    #[Test]
    public function it_works_for_a_registered_customers_order_with_no_guest_access_token(): void
    {
        $order = Order::factory()->create([
            'user_id' => User::factory()->create()->id,
            'guest_access_token' => null,
            'customer_email' => 'registered@example.com',
            'customer_first_name' => 'Maria',
        ]);

        $signedUrl = URL::temporarySignedRoute('orders.review-identity', now()->addDays(30), ['order' => $order->id]);

        // No auth:sanctum session at all — the signature alone must be enough.
        $response = $this->getJson($signedUrl);

        $response->assertOk();
        $response->assertJsonPath('data.email', 'registered@example.com');
    }

    #[Test]
    public function an_unsigned_request_is_rejected(): void
    {
        $order = Order::factory()->create();

        $this->getJson("/api/v1/orders/{$order->id}/review-identity")->assertForbidden();
    }

    #[Test]
    public function a_tampered_link_is_rejected(): void
    {
        $order = Order::factory()->create();
        $signedUrl = URL::temporarySignedRoute('orders.review-identity', now()->addDays(30), ['order' => $order->id]);

        $this->getJson($signedUrl.'&tampered=1')->assertForbidden();
    }

    #[Test]
    public function an_expired_link_is_rejected(): void
    {
        $order = Order::factory()->create();
        $signedUrl = URL::temporarySignedRoute('orders.review-identity', now()->subDay(), ['order' => $order->id]);

        $this->getJson($signedUrl)->assertForbidden();
    }
}
