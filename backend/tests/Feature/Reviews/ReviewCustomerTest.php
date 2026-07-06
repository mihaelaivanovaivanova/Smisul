<?php

namespace Tests\Feature\Reviews;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReviewCustomerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_customer_can_list_only_their_own_reviews(): void
    {
        $customer = User::factory()->create();
        Review::factory()->for($customer)->create();
        Review::factory()->create(); // someone else's

        $response = $this->actingAs($customer)->getJson('/api/v1/customer/reviews');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function a_customer_can_edit_their_own_review_at_any_time(): void
    {
        $customer = User::factory()->create();
        // Reviews publish immediately (status: Approved) — editing isn't
        // restricted to a pending window that no longer exists.
        $review = Review::factory()->for($customer)->create(['rating' => 3, 'title' => 'Okay']);

        $response = $this->actingAs($customer)->putJson("/api/v1/customer/reviews/{$review->id}", [
            'rating' => 5,
            'title' => 'Actually great',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.rating', 5);
        $response->assertJsonPath('data.title', 'Actually great');
    }

    #[Test]
    public function editing_a_review_does_not_change_its_moderation_status(): void
    {
        $customer = User::factory()->create();
        $review = Review::factory()->for($customer)->hidden()->create();

        $response = $this->actingAs($customer)->putJson("/api/v1/customer/reviews/{$review->id}", ['title' => 'Edited']);

        $response->assertOk();
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'status' => 'hidden']);
    }

    #[Test]
    public function a_customer_cannot_edit_another_customers_review(): void
    {
        $customer = User::factory()->create();
        $review = Review::factory()->create(); // belongs to someone else

        $this->actingAs($customer)->putJson("/api/v1/customer/reviews/{$review->id}", ['rating' => 1])
            ->assertForbidden();
    }

    #[Test]
    public function a_customer_can_delete_their_own_review_at_any_time(): void
    {
        $customer = User::factory()->create();
        $review = Review::factory()->for($customer)->create(); // approved

        $this->actingAs($customer)->deleteJson("/api/v1/customer/reviews/{$review->id}")
            ->assertNoContent();

        $this->assertModelMissing($review);
    }

    #[Test]
    public function a_customer_cannot_delete_another_customers_review(): void
    {
        $customer = User::factory()->create();
        $review = Review::factory()->create(); // belongs to someone else

        $this->actingAs($customer)->deleteJson("/api/v1/customer/reviews/{$review->id}")
            ->assertForbidden();

        $this->assertModelExists($review);
    }
}
