<?php

namespace Tests\Feature\Reviews;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReviewHelpfulTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_guest_cannot_mark_a_review_helpful(): void
    {
        $review = Review::factory()->create();

        $this->postJson("/api/v1/reviews/{$review->id}/helpful")->assertUnauthorized();
    }

    #[Test]
    public function an_authenticated_user_can_mark_a_review_helpful(): void
    {
        $voter = User::factory()->create();
        $review = Review::factory()->create();

        $response = $this->actingAs($voter)->postJson("/api/v1/reviews/{$review->id}/helpful");

        $response->assertOk();
        $response->assertJsonPath('data.is_helpful', true);
        $response->assertJsonPath('data.helpful_count', 1);
        $this->assertDatabaseHas('review_votes', ['review_id' => $review->id, 'user_id' => $voter->id]);
    }

    #[Test]
    public function voting_helpful_again_withdraws_the_vote(): void
    {
        $voter = User::factory()->create();
        $review = Review::factory()->create();

        $this->actingAs($voter)->postJson("/api/v1/reviews/{$review->id}/helpful")->assertOk();
        $response = $this->actingAs($voter)->postJson("/api/v1/reviews/{$review->id}/helpful");

        $response->assertOk();
        $response->assertJsonPath('data.is_helpful', false);
        $response->assertJsonPath('data.helpful_count', 0);
        $this->assertDatabaseMissing('review_votes', ['review_id' => $review->id, 'user_id' => $voter->id]);
    }

    #[Test]
    public function multiple_users_voting_helpful_accumulates_the_count(): void
    {
        $review = Review::factory()->create();
        $voters = User::factory()->count(3)->create();

        foreach ($voters as $voter) {
            $this->actingAs($voter)->postJson("/api/v1/reviews/{$review->id}/helpful")->assertOk();
        }

        $this->assertSame(3, $review->fresh()->helpful_count);
    }

    #[Test]
    public function a_user_cannot_mark_their_own_review_helpful(): void
    {
        $author = User::factory()->create();
        $review = Review::factory()->for($author)->create();

        $this->actingAs($author)->postJson("/api/v1/reviews/{$review->id}/helpful")->assertForbidden();
    }

    #[Test]
    public function a_pending_review_cannot_be_marked_helpful(): void
    {
        $voter = User::factory()->create();
        $review = Review::factory()->pending()->create();

        $this->actingAs($voter)->postJson("/api/v1/reviews/{$review->id}/helpful")->assertForbidden();
    }
}
