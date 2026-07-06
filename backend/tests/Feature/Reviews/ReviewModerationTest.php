<?php

namespace Tests\Feature\Reviews;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewApprovedNotification;
use App\Notifications\ReviewRejectedNotification;
use App\Notifications\ReviewReplyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_guest_cannot_access_admin_review_routes(): void
    {
        $review = Review::factory()->pending()->create();

        $this->getJson('/api/v1/admin/reviews')->assertUnauthorized();
        $this->postJson("/api/v1/admin/reviews/{$review->id}/approve")->assertUnauthorized();
    }

    #[Test]
    public function a_customer_cannot_access_admin_review_routes(): void
    {
        $customer = User::factory()->create();
        $review = Review::factory()->pending()->create();

        $this->actingAs($customer)->postJson("/api/v1/admin/reviews/{$review->id}/approve")->assertForbidden();
    }

    #[Test]
    public function only_approved_reviews_are_publicly_visible(): void
    {
        Notification::fake();

        $product = Product::factory()->published()->create();
        $approved = Review::factory()->for($product)->create(['title' => 'Approved review']);
        Review::factory()->for($product)->pending()->create(['title' => 'Pending review']);
        Review::factory()->for($product)->rejected()->create(['title' => 'Rejected review']);
        Review::factory()->for($product)->hidden()->create(['title' => 'Hidden review']);

        $response = $this->getJson("/api/v1/products/{$product->slug}/reviews");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Approved review');
        $response->assertJsonPath('data.0.id', $approved->id);
    }

    #[Test]
    public function an_admin_can_approve_a_pending_review_and_the_author_is_notified(): void
    {
        Notification::fake();

        $admin = User::factory()->administrator()->create();
        $author = User::factory()->create();
        $review = Review::factory()->for($author)->pending()->create();

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/reviews/{$review->id}/approve");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'approved');
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'status' => 'approved']);
        Notification::assertSentTo($author, ReviewApprovedNotification::class);
    }

    #[Test]
    public function an_admin_can_reject_a_pending_review_with_a_reason_and_the_author_is_notified(): void
    {
        Notification::fake();

        $admin = User::factory()->administrator()->create();
        $author = User::factory()->create();
        $review = Review::factory()->for($author)->pending()->create();

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/reviews/{$review->id}/reject", [
            'reason' => 'Contains inappropriate language',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'rejected');
        Notification::assertSentTo($author, function (ReviewRejectedNotification $notification) {
            return $notification->reason === 'Contains inappropriate language';
        });
    }

    #[Test]
    public function an_admin_can_hide_an_approved_review_without_notifying_the_author(): void
    {
        Notification::fake();

        $admin = User::factory()->administrator()->create();
        $author = User::factory()->create();
        $review = Review::factory()->for($author)->create(); // approved

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/reviews/{$review->id}/hide");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'hidden');
        Notification::assertNothingSentTo($author);
    }

    #[Test]
    public function an_admin_can_reply_to_a_review_and_the_author_is_notified(): void
    {
        Notification::fake();

        $admin = User::factory()->administrator()->create();
        $author = User::factory()->create();
        $review = Review::factory()->for($author)->create();

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/reviews/{$review->id}/reply", [
            'reply' => 'Thank you for your feedback!',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.admin_reply', 'Thank you for your feedback!');
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'admin_replied_by' => $admin->id]);
        Notification::assertSentTo($author, ReviewReplyNotification::class);
    }

    #[Test]
    public function an_admin_can_bulk_moderate_several_reviews_at_once(): void
    {
        Notification::fake();

        $admin = User::factory()->administrator()->create();
        $reviews = Review::factory()->pending()->count(3)->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/reviews/bulk-moderate', [
            'review_ids' => $reviews->pluck('id')->all(),
            'status' => 'approved',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.updated', 3);

        foreach ($reviews as $review) {
            $this->assertDatabaseHas('reviews', ['id' => $review->id, 'status' => 'approved']);
        }
    }

    #[Test]
    public function an_admin_can_filter_the_review_list_by_status(): void
    {
        $admin = User::factory()->administrator()->create();
        Review::factory()->pending()->count(2)->create();
        Review::factory()->count(3)->create(); // approved

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/reviews?status=pending');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function an_admin_can_see_review_statistics(): void
    {
        $admin = User::factory()->administrator()->create();
        Review::factory()->pending()->count(2)->create();
        Review::factory()->create(['rating' => 4]);
        Review::factory()->create(['rating' => 2]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/reviews/statistics');

        $response->assertOk();
        $response->assertJsonPath('data.total_reviews', 4);
        $response->assertJsonPath('data.pending_count', 2);
        $response->assertJsonPath('data.average_rating', 3);
    }
}
