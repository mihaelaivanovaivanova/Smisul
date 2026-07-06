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
    public function a_customer_can_edit_their_review_while_pending(): void
    {
        $customer = User::factory()->create();
        $review = Review::factory()->for($customer)->pending()->create(['rating' => 3, 'title' => 'Okay']);

        $response = $this->actingAs($customer)->putJson("/api/v1/customer/reviews/{$review->id}", [
            'rating' => 5,
            'title' => 'Actually great',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.rating', 5);
        $response->assertJsonPath('data.title', 'Actually great');
    }

    #[Test]
    public function a_customer_cannot_edit_a_review_once_it_has_been_approved(): void
    {
        $customer = User::factory()->create();
        $review = Review::factory()->for($customer)->create(); // default status: Approved

        $this->actingAs($customer)->putJson("/api/v1/customer/reviews/{$review->id}", ['rating' => 1])
            ->assertForbidden();
    }

    #[Test]
    public function a_customer_cannot_edit_another_customers_review(): void
    {
        $customer = User::factory()->create();
        $review = Review::factory()->pending()->create(); // belongs to someone else

        $this->actingAs($customer)->putJson("/api/v1/customer/reviews/{$review->id}", ['rating' => 1])
            ->assertForbidden();
    }

    #[Test]
    public function a_customer_can_delete_their_own_review_while_pending(): void
    {
        $customer = User::factory()->create();
        $review = Review::factory()->for($customer)->pending()->create();

        $this->actingAs($customer)->deleteJson("/api/v1/customer/reviews/{$review->id}")
            ->assertNoContent();

        $this->assertModelMissing($review);
    }

    #[Test]
    public function a_customer_cannot_delete_a_review_once_it_has_been_approved(): void
    {
        $customer = User::factory()->create();
        $review = Review::factory()->for($customer)->create(); // default status: Approved

        $this->actingAs($customer)->deleteJson("/api/v1/customer/reviews/{$review->id}")
            ->assertForbidden();

        $this->assertModelExists($review);
    }
}
