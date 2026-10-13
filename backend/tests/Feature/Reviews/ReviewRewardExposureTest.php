<?php

namespace Tests\Feature\Reviews;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The on-site reveal of a just-issued reward code - shown directly in the
 * API response of whatever action just approved the review, rather than
 * requiring the customer to go check their email (see ReviewController::
 * rewardPayload()/ProductController::submitReview()'s preVerified branch).
 */
class ReviewRewardExposureTest extends TestCase
{
    use RefreshDatabase;

    private function deliveredOrderWithItem(ProductVariant $variant, ?User $user, string $email): Order
    {
        return tap(Order::factory()->create([
            'user_id' => $user?->id,
            'status' => OrderStatus::Delivered,
            'customer_email' => $email,
            'customer_first_name' => 'Ivan',
        ]), function (Order $order) use ($variant) {
            OrderItem::factory()->for($order)->create(['product_variant_id' => $variant->id]);
        });
    }

    #[Test]
    public function submitting_via_a_pre_verified_reminder_link_returns_the_reward_code_directly(): void
    {
        Mail::fake();

        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $order = $this->deliveredOrderWithItem($variant, null, 'ivan@example.com');
        $signedUrl = URL::temporarySignedRoute('orders.review-identity', now()->addDays(30), ['order' => $order->id]);
        parse_str(parse_url($signedUrl, PHP_URL_QUERY), $signedParams);

        $response = $this->postJson("/api/v1/products/{$product->slug}/reviews/submit", [
            'rating' => 5,
            'body' => 'Great stuff.',
            'email' => 'ivan@example.com',
            'display_name' => 'Ivan',
            'order_id' => $order->id,
            'expires' => $signedParams['expires'],
            'signature' => $signedParams['signature'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('reward.discount_percent', 10);
        $this->assertNotNull($response->json('reward.code'));
        $this->assertNotNull($response->json('reward.expires_at'));

        $this->assertDatabaseHas('discount_codes', ['code' => $response->json('reward.code'), 'customer_email' => 'ivan@example.com']);
    }

    #[Test]
    public function the_normal_guest_wizard_without_a_signed_link_still_gets_the_generic_response_with_no_reward_leak(): void
    {
        Mail::fake();

        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $this->deliveredOrderWithItem($variant, null, 'ivan@example.com');

        $response = $this->postJson("/api/v1/products/{$product->slug}/reviews/submit", [
            'rating' => 5,
            'body' => 'Great stuff.',
            'email' => 'ivan@example.com',
            'display_name' => 'Ivan',
        ]);

        $response->assertOk();
        $response->assertExactJson([
            'message' => 'Ако имейлът отговаря на доставена поръчка за този продукт, ще получиш писмо за потвърждение.',
        ]);
    }

    #[Test]
    public function confirming_via_the_emailed_link_returns_the_reward_code_too(): void
    {
        Mail::fake();

        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $this->deliveredOrderWithItem($variant, null, 'ivan@example.com');

        $this->postJson("/api/v1/products/{$product->slug}/reviews/submit", [
            'rating' => 5,
            'body' => 'Great stuff.',
            'email' => 'ivan@example.com',
            'display_name' => 'Ivan',
        ])->assertOk();

        $review = Review::query()->where('email', 'ivan@example.com')->firstOrFail();
        $signedUrl = URL::temporarySignedRoute('reviews.confirm', now()->addHours(48), ['review' => $review->id]);
        parse_str(parse_url($signedUrl, PHP_URL_QUERY), $signedParams);

        $response = $this->getJson("/api/v1/reviews/{$review->id}/confirm?".http_build_query($signedParams));

        $response->assertOk();
        $response->assertJsonPath('reward.discount_percent', 10);
        $this->assertNotNull($response->json('reward.code'));
    }

    /**
     * Reproduces a real bug found while manually testing this flow locally:
     * ReviewApproved now fires synchronously on the common "review just got
     * approved" paths (not just the rare manual admin approve()), and
     * SendReviewApprovedNotification had no try/catch - a real transport
     * failure there 500'd the whole submission request even though the
     * review (and, worse, the reward code) was already saved. Mirrors
     * ReviewSubmissionTest's own a_mail_transport_failure_does_not_fail_the_
     * submission_request - a real unreachable port, not Mail::fake(), since
     * a fake never exercises the try/catch at all.
     */
    #[Test]
    public function a_notification_mail_failure_does_not_fail_the_submission_or_lose_the_reward(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $order = $this->deliveredOrderWithItem($variant, null, 'ivan@example.com');
        $signedUrl = URL::temporarySignedRoute('orders.review-identity', now()->addDays(30), ['order' => $order->id]);
        parse_str(parse_url($signedUrl, PHP_URL_QUERY), $signedParams);

        $response = $this->postJson("/api/v1/products/{$product->slug}/reviews/submit", [
            'rating' => 5,
            'body' => 'Great stuff.',
            'email' => 'ivan@example.com',
            'display_name' => 'Ivan',
            'order_id' => $order->id,
            'expires' => $signedParams['expires'],
            'signature' => $signedParams['signature'],
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('reward.code'));
        $this->assertDatabaseHas('discount_codes', ['code' => $response->json('reward.code')]);
    }
}
