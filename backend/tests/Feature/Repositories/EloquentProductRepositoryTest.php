<?php

namespace Tests\Feature\Repositories;

use App\DataTransferObjects\ProductFilterData;
use App\Enums\Currency;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Repositories\EloquentProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EloquentProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentProductRepository;
    }

    private function productWithPrice(array $productAttributes, float $amount): Product
    {
        $product = Product::factory()->published()->create($productAttributes);
        $variant = $product->variants()->create([
            'sku' => 'SKU-'.$product->id.'-'.uniqid(),
            'name' => '1-pack',
            'pack_size' => 1,
        ]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => $amount]);

        return $product;
    }

    #[Test]
    public function find_by_slug_only_returns_published_products_by_default(): void
    {
        Product::factory()->create(['slug' => 'a-draft', 'status' => ProductStatus::Draft]);

        $this->assertNull($this->repository->findBySlug('a-draft'));
        $this->assertNotNull($this->repository->findBySlug('a-draft', publishedOnly: false));
    }

    #[Test]
    public function paginate_only_includes_published_products_when_restricted(): void
    {
        Product::factory()->published()->create(['name' => 'Visible Product']);
        Product::factory()->create(['name' => 'Hidden Draft', 'status' => ProductStatus::Draft]);

        $results = $this->repository->paginate(new ProductFilterData, publishedOnly: true);

        $this->assertCount(1, $results->items());
        $this->assertSame('Visible Product', $results->items()[0]->name);
    }

    #[Test]
    public function it_filters_by_search_term_against_name(): void
    {
        Product::factory()->published()->create(['name' => 'Chamomile Tea']);
        Product::factory()->published()->create(['name' => 'Green Coffee']);

        $results = $this->repository->paginate(new ProductFilterData(search: 'chamomile'));

        $this->assertCount(1, $results->items());
        $this->assertSame('Chamomile Tea', $results->items()[0]->name);
    }

    #[Test]
    public function it_filters_by_category_slug(): void
    {
        $category = Category::factory()->create(['slug' => 'teas']);
        $inCategory = Product::factory()->published()->create();
        $inCategory->categories()->attach($category);
        Product::factory()->published()->create();

        $results = $this->repository->paginate(new ProductFilterData(categorySlug: 'teas'));

        $this->assertCount(1, $results->items());
        $this->assertTrue($results->items()[0]->is($inCategory));
    }

    #[Test]
    public function it_filters_by_price_range(): void
    {
        $this->productWithPrice(['name' => 'Cheap'], 10.00);
        $this->productWithPrice(['name' => 'Mid'], 25.00);
        $this->productWithPrice(['name' => 'Expensive'], 100.00);

        $results = $this->repository->paginate(new ProductFilterData(minPrice: 20, maxPrice: 50));

        $this->assertCount(1, $results->items());
        $this->assertSame('Mid', $results->items()[0]->name);
    }

    #[Test]
    public function it_sorts_by_price_ascending_and_descending(): void
    {
        $this->productWithPrice(['name' => 'Cheap'], 10.00);
        $this->productWithPrice(['name' => 'Expensive'], 100.00);

        $ascending = $this->repository->paginate(new ProductFilterData(sort: 'price_asc'));
        $descending = $this->repository->paginate(new ProductFilterData(sort: 'price_desc'));

        $this->assertSame('Cheap', $ascending->items()[0]->name);
        $this->assertSame('Expensive', $descending->items()[0]->name);
    }

    #[Test]
    public function it_sorts_alphabetically_by_name(): void
    {
        Product::factory()->published()->create(['name' => 'Zebra']);
        Product::factory()->published()->create(['name' => 'Apple']);

        $results = $this->repository->paginate(new ProductFilterData(sort: 'name'));

        $this->assertSame('Apple', $results->items()[0]->name);
        $this->assertSame('Zebra', $results->items()[1]->name);
    }

    #[Test]
    public function it_paginates_results(): void
    {
        Product::factory()->published()->count(5)->create();

        $results = $this->repository->paginate(new ProductFilterData(perPage: 2, page: 2));

        $this->assertCount(2, $results->items());
        $this->assertSame(5, $results->total());
        $this->assertSame(2, $results->currentPage());
    }
}
