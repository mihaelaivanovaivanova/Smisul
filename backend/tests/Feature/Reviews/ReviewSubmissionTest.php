<?php

namespace Tests\Feature\Reviews;

use App\Enums\OrderStatus;
use App\Mail\ReviewConfirmationMail;
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

class ReviewSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function deliveredOrderWithItem(ProductVariant $variant, ?User $user, string $email): Order
    {
        $order = Order::factory()->create([
            'user_id' => $user?->id,
            'customer_email' => $email,
            'status' => OrderStatus::Delivered,
        ]);
        OrderItem::factory()->for($order)->for($variant, 'productVariant')->create();

        return $order;
    }

    /**
     * No 'title' by default — the guest wizard never collects one (see
     * SubmitReviewRequest, which accepts it as nullable purely for the
     * schema's sake, not because the frontend ever sends it).
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'rating' => 5,
            'body' => 'Really happy with this purchase, would buy again.',
            'email' => 'reviewer@example.com',
            'display_name' => 'Reviewer R.',
            'is_anonymous' => false,
        ], $overrides);
    }

    #[Test]
    public function a_guest_order_can_be_reviewed_by_matching_customer_email(): void
    {
        Mail::fake();

        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $order = $this->deliveredOrderWithItem($variant, null, 'guest@example.com');

        $response = $this->postJson(
            "/api/v1/products/{$product->slug}/reviews/submit",
            $this->payload(['email' => 'guest@example.com']),
        );

        $response->assertOk();
        $this->assertDatabaseHas('reviews', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'email' => 'guest@example.com',
            'display_name' => 'Reviewer R.',
            'status' => 'pending',
        ]);
        $review = Review::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertNull($review->confirmed_at);
        $this->assertNull($review->user_id);
        $this->assertNull($review->title);

        Mail::assertSent(ReviewConfirmationMail::class, fn (ReviewConfirmationMail $mail) => $mail->hasTo('guest@example.com'));
    }

    #[Test]
    public function an_authenticated_customers_order_can_be_reviewed_by_matching_email_too(): void
    {
        Mail::fake();

        $customer = User::factory()->create(['email' => 'account-holder@example.com']);
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $this->deliveredOrderWithItem($variant, $customer, 'account-holder@example.com');

        $response = $this->postJson(
            "/api/v1/products/{$product->slug}/reviews/submit",
            $this->payload(['email' => 'account-holder@example.com']),
        );

        $response->assertOk();
        $this->assertDatabaseHas('reviews', ['email' => 'account-holder@example.com', 'status' => 'pending']);
    }

    #[Test]
    public function email_matching_is_case_insensitive(): void
    {
        Mail::fake();

        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $this->deliveredOrderWithItem($variant, null, 'MixedCase@Example.com');

        $response = $this->postJson(
            "/api/v1/products/{$product->slug}/reviews/submit",
            $this->payload(['email' => 'mixedcase@example.com']),
        );

        $response->assertOk();
        $this->assertDatabaseCount('reviews', 1);
    }

    #[Test]
    public function a_non_matching_email_still_returns_the_generic_success_response_and_creates_nothing(): void
    {
        Mail::fake();

        $product = Product::factory()->published()->create();

        $response = $this->postJson(
            "/api/v1/products/{$product->slug}/reviews/submit",
            $this->payload(['email' => 'never-ordered@example.com']),
        );

        $response->assertOk();
        $this->assertDatabaseCount('reviews', 0);
        Mail::assertNothingSent();
    }

    #[Test]
    public function an_already_reviewed_order_creates_nothing(): void
    {
        Mail::fake();

        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $order = $this->deliveredOrderWithItem($variant, null, 'guest@example.com');
        Review::factory()->for($product)->create(['order_id' => $order->id, 'email' => 'guest@example.com']);

        $response = $this->postJson(
            "/api/v1/products/{$product->slug}/reviews/submit",
            $this->payload(['email' => 'guest@example.com']),
        );

        $response->assertOk();
        $this->assertDatabaseCount('reviews', 1);
    }

    #[Test]
    public function an_email_cannot_review_the_same_product_twice_even_from_a_different_delivered_order(): void
    {
        Mail::fake();

        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $firstOrder = $this->deliveredOrderWithItem($variant, null, 'guest@example.com');
        $this->deliveredOrderWithItem($variant, null, 'guest@example.com');
        Review::factory()->for($product)->create(['order_id' => $firstOrder->id, 'email' => 'guest@example.com']);

        $response = $this->postJson(
            "/api/v1/products/{$product->slug}/reviews/submit",
            $this->payload(['email' => 'guest@example.com']),
        );

        $response->assertOk();
        $this->assertDatabaseCount('reviews', 1);
    }

    #[Test]
    public function an_unconfirmed_submission_already_blocks_a_second_one_for_the_same_email(): void
    {
        Mail::fake();

        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $this->deliveredOrderWithItem($variant, null, 'guest@example.com');

        $this->postJson(
            "/api/v1/products/{$product->slug}/reviews/submit",
            $this->payload(['email' => 'guest@example.com']),
        )->assertOk();
        $this->assertDatabaseCount('reviews', 1);

        $this->postJson(
            "/api/v1/products/{$product->slug}/reviews/submit",
            $this->payload(['email' => 'guest@example.com', 'body' => 'A second attempt.']),
        )->assertOk();

        $this->assertDatabaseCount('reviews', 1);
    }

    #[Test]
    public function a_mail_transport_failure_does_not_fail_the_submission_request(): void
    {
        // Points the mailer at a port nothing is listening on, so sending
        // the confirmation email throws for real (not Mail::fake())
        // — reproduces the exact bug this test guards against: a transport
        // failure must be caught and logged, never surfaced as a 500 to the
        // visitor, since the review row itself is already saved by then.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $this->deliveredOrderWithItem($variant, null, 'guest@example.com');

        $response = $this->postJson(
            "/api/v1/products/{$product->slug}/reviews/submit",
            $this->payload(['email' => 'guest@example.com']),
        );

        $response->assertOk();
        $this->assertDatabaseHas('reviews', ['email' => 'guest@example.com', 'status' => 'pending']);
    }

    #[Test]
    public function submitting_requires_a_valid_rating_and_email(): void
    {
        $product = Product::factory()->published()->create();

        $this->postJson("/api/v1/products/{$product->slug}/reviews/submit", $this->payload(['rating' => 6]))
            ->assertUnprocessable();

        $this->postJson("/api/v1/products/{$product->slug}/reviews/submit", $this->payload(['email' => 'not-an-email']))
            ->assertUnprocessable();
    }

    #[Test]
    public function confirming_via_the_signed_link_publishes_the_review_and_makes_it_publicly_visible(): void
    {
        $product = Product::factory()->published()->create();
        $review = Review::factory()->pending()->for($product)->create(['confirmed_at' => null]);

        $signedUrl = URL::temporarySignedRoute('reviews.confirm', now()->addHours(48), ['review' => $review->id]);

        $response = $this->getJson($signedUrl);

        // status isn't in the JSON here — ReviewResource only includes it
        // for the review's own author (see its `is_own` check), and this
        // request is unauthenticated, same as any other public read.
        $response->assertOk();
        $review->refresh();
        $this->assertSame('approved', $review->status->value);
        $this->assertNotNull($review->confirmed_at);

        $this->getJson("/api/v1/products/{$product->slug}/reviews")->assertJsonCount(1, 'data');
    }

    #[Test]
    public function confirming_twice_is_a_no_op_not_an_error(): void
    {
        $review = Review::factory()->pending()->create(['confirmed_at' => null]);
        $signedUrl = URL::temporarySignedRoute('reviews.confirm', now()->addHours(48), ['review' => $review->id]);

        $this->getJson($signedUrl)->assertOk();
        $confirmedAt = $review->refresh()->confirmed_at;

        $this->getJson($signedUrl)->assertOk();
        $this->assertTrue($confirmedAt->equalTo($review->refresh()->confirmed_at));
    }

    #[Test]
    public function a_tampered_confirmation_link_is_rejected(): void
    {
        $review = Review::factory()->pending()->create(['confirmed_at' => null]);
        $signedUrl = URL::temporarySignedRoute('reviews.confirm', now()->addHours(48), ['review' => $review->id]);

        $this->getJson($signedUrl.'&tampered=1')->assertForbidden();
        $this->assertNull($review->refresh()->confirmed_at);
    }
}
