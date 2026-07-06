<?php

namespace Tests\Feature\Reviews;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReviewSummaryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_summary_averages_only_approved_reviews(): void
    {
        $product = Product::factory()->published()->create();
        Review::factory()->for($product)->create(['rating' => 5]);
        Review::factory()->for($product)->create(['rating' => 3]);
        Review::factory()->for($product)->pending()->create(['rating' => 1]);
        Review::factory()->for($product)->rejected()->create(['rating' => 1]);

        $response = $this->getJson("/api/v1/products/{$product->slug}/reviews/summary");

        $response->assertOk();
        $response->assertJsonPath('data.review_count', 2);
        // A whole-number average round-trips through JSON as an int (no
        // JSON_PRESERVE_ZERO_FRACTION), so assert 4, not 4.0.
        $response->assertJsonPath('data.average_rating', 4);
    }

    #[Test]
    public function the_summary_reports_a_star_rating_distribution(): void
    {
        $product = Product::factory()->published()->create();
        Review::factory()->for($product)->create(['rating' => 5]);
        Review::factory()->for($product)->create(['rating' => 5]);
        Review::factory()->for($product)->create(['rating' => 3]);
        Review::factory()->for($product)->create(['rating' => 1]);

        $response = $this->getJson("/api/v1/products/{$product->slug}/reviews/summary");

        $response->assertOk();
        $response->assertJsonPath('data.distribution.5', 2);
        $response->assertJsonPath('data.distribution.4', 0);
        $response->assertJsonPath('data.distribution.3', 1);
        $response->assertJsonPath('data.distribution.2', 0);
        $response->assertJsonPath('data.distribution.1', 1);
    }

    #[Test]
    public function the_summary_counts_verified_purchases(): void
    {
        $product = Product::factory()->published()->create();
        Review::factory()->for($product)->create(['verified_purchase' => true]);
        Review::factory()->for($product)->create(['verified_purchase' => true]);

        $response = $this->getJson("/api/v1/products/{$product->slug}/reviews/summary");

        $response->assertOk();
        $response->assertJsonPath('data.verified_count', 2);
    }

    #[Test]
    public function a_product_with_no_reviews_has_a_zeroed_summary(): void
    {
        $product = Product::factory()->published()->create();

        $response = $this->getJson("/api/v1/products/{$product->slug}/reviews/summary");

        $response->assertOk();
        $response->assertJsonPath('data.review_count', 0);
        $response->assertJsonPath('data.average_rating', 0);
    }

    #[Test]
    public function reviews_can_be_sorted_by_highest_and_lowest_rating(): void
    {
        $product = Product::factory()->published()->create();
        $low = Review::factory()->for($product)->create(['rating' => 1]);
        $high = Review::factory()->for($product)->create(['rating' => 5]);

        $highest = $this->getJson("/api/v1/products/{$product->slug}/reviews?sort=highest");
        $highest->assertJsonPath('data.0.id', $high->id);

        $lowest = $this->getJson("/api/v1/products/{$product->slug}/reviews?sort=lowest");
        $lowest->assertJsonPath('data.0.id', $low->id);
    }
}
