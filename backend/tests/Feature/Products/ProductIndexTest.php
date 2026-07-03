<?php

namespace Tests\Feature\Products;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductIndexTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_only_published_products(): void
    {
        Product::factory()->published()->create(['name' => 'Visible']);
        Product::factory()->create(['name' => 'Draft', 'status' => ProductStatus::Draft]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Visible');
    }

    #[Test]
    public function it_paginates_results(): void
    {
        Product::factory()->published()->count(20)->create();

        $response = $this->getJson('/api/v1/products?per_page=5');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
        $response->assertJsonPath('meta.total', 20);
        $response->assertJsonPath('meta.per_page', 5);
    }

    #[Test]
    public function it_validates_the_sort_parameter(): void
    {
        $this->getJson('/api/v1/products?sort=not-a-real-sort')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    #[Test]
    public function it_validates_that_max_price_is_not_below_min_price(): void
    {
        $this->getJson('/api/v1/products?min_price=50&max_price=10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('max_price');
    }

    #[Test]
    public function it_searches_by_name(): void
    {
        Product::factory()->published()->create(['name' => 'Chamomile Tea']);
        Product::factory()->published()->create(['name' => 'Green Coffee']);

        $response = $this->getJson('/api/v1/products?search=chamomile');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Chamomile Tea');
    }
}
