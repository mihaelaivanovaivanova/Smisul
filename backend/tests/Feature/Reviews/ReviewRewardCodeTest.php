<?php

namespace Tests\Feature\Reviews;

use App\Enums\OrderStatus;
use App\Mail\ReviewRewardMail;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReviewRewardCodeTest extends TestCase
{
    use RefreshDatabase;

    private function deliveredOrderWithItem(ProductVariant $variant, ?User $user, string $email): Order
    {
        $order = Order::factory()->create([
            'user_id' => $user?->id,
            'status' => OrderStatus::Delivered,
            'customer_email' => $email,
            'customer_first_name' => 'Ivan',
        ]);

        OrderItem::factory()->for($order)->create(['product_variant_id' => $variant->id]);

        return $order;
    }

    #[Test]
    public function approving_a_review_issues_a_single_use_ten_percent_code_emailed_to_the_reviewer(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'ivan@example.com']);
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $order = $this->deliveredOrderWithItem($variant, $user, 'ivan@example.com');

        app(ReviewService::class)->create($user, $variant, $order, [
            'rating' => 5,
            'title' => 'Great',
            'body' => 'Loved it.',
        ]);

        // whereNotNull('review_id'), not assertDatabaseCount on the whole
        // table - a promo code unrelated to any review (e.g. a seeded
        // marketing code, review_id always null for those) can coexist in
        // the table without this test caring about it.
        $this->assertSame(1, DiscountCode::whereNotNull('review_id')->count());
        $discountCode = DiscountCode::whereNotNull('review_id')->first();
        $this->assertSame('ivan@example.com', $discountCode->customer_email);
        $this->assertEquals(10, $discountCode->discount_percent);
        $this->assertSame(1, $discountCode->max_uses);
        $this->assertNotNull($discountCode->expires_at);
        $this->assertTrue($discountCode->expires_at->isBetween(now()->addDays(29), now()->addDays(31)));

        Mail::assertSent(ReviewRewardMail::class, fn (ReviewRewardMail $mail) => $mail->hasTo('ivan@example.com')
            && $mail->discountCode->is($discountCode));
    }

    #[Test]
    public function a_review_can_only_ever_issue_one_reward_code(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'ivan@example.com']);
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $order = $this->deliveredOrderWithItem($variant, $user, 'ivan@example.com');

        $review = app(ReviewService::class)->create($user, $variant, $order, [
            'rating' => 5,
            'title' => 'Great',
            'body' => 'Loved it.',
        ]);

        // Re-approving an already-approved review (e.g. via admin bulk
        // moderation) must not mint a second code or re-send the email.
        app(ReviewService::class)->approve($review);

        $this->assertSame(1, DiscountCode::whereNotNull('review_id')->count());
        Mail::assertSentCount(1);
    }

    #[Test]
    public function a_guest_review_with_no_user_account_still_gets_a_reward_code(): void
    {
        Mail::fake();

        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $order = $this->deliveredOrderWithItem($variant, null, 'guest@example.com');

        $review = Review::factory()->for($product)->create([
            'order_id' => $order->id,
            'user_id' => null,
            'email' => 'guest@example.com',
            'status' => 'pending',
        ]);

        app(ReviewService::class)->approve($review);

        $this->assertDatabaseHas('discount_codes', ['customer_email' => 'guest@example.com']);
        Mail::assertSent(ReviewRewardMail::class, fn (ReviewRewardMail $mail) => $mail->hasTo('guest@example.com'));
    }
}
