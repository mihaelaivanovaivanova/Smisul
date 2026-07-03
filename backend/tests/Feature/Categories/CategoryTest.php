<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_active_root_categories_with_nested_children(): void
    {
        $root = Category::factory()->create(['name' => 'Teas']);
        Category::factory()->childOf($root)->create(['name' => 'Herbal']);
        Category::factory()->inactive()->create(['name' => 'Hidden']);

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Teas');
        $response->assertJsonCount(1, 'data.0.children');
    }

    #[Test]
    public function it_shows_a_category_by_slug(): void
    {
        $category = Category::factory()->create(['slug' => 'teas']);

        $this->getJson('/api/v1/categories/teas')
            ->assertOk()
            ->assertJsonPath('data.slug', 'teas');
    }

    #[Test]
    public function it_returns_404_for_an_unknown_category_slug(): void
    {
        $this->getJson('/api/v1/categories/does-not-exist')->assertNotFound();
    }

    #[Test]
    public function it_lists_published_products_within_a_category(): void
    {
        $category = Category::factory()->create(['slug' => 'teas']);
        $inCategory = Product::factory()->published()->create();
        $inCategory->categories()->attach($category);
        Product::factory()->published()->create();

        $response = $this->getJson('/api/v1/categories/teas/products');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }
}
