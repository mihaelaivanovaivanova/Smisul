<?php

namespace Tests\Unit\Services;

use App\DataTransferObjects\ProductData;
use App\DataTransferObjects\ProductFilterData;
use App\Enums\ProductStatus;
use App\Exceptions\ProductNotFoundException;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\InventoryService;
use App\Services\PriceService;
use App\Services\ProductService;
use App\Services\ProductVariantService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ProductRepositoryInterface is mocked throughout — ProductService's own
 * logic (publish timestamps, not-found translation) is verified without
 * depending on EloquentProductRepository's query correctness at all.
 *
 * RefreshDatabase is still needed: create()/update() call
 * $product->categories()->sync() directly against the real category_product
 * pivot table as a side effect, independent of the mocked repository.
 */
class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(ProductRepositoryInterface $repository): ProductService
    {
        return new ProductService($repository, new ProductVariantService, new PriceService, new InventoryService);
    }

    #[Test]
    public function find_by_slug_throws_a_domain_exception_when_the_repository_returns_null(): void
    {
        $repository = Mockery::mock(ProductRepositoryInterface::class);
        $repository->shouldReceive('findBySlug')->with('missing', true)->andReturnNull();

        $service = $this->makeService($repository);

        $this->expectException(ProductNotFoundException::class);

        $service->findBySlug('missing');
    }

    #[Test]
    public function find_by_slug_returns_the_product_the_repository_provides(): void
    {
        $product = Product::factory()->make(['slug' => 'found-me']);

        $repository = Mockery::mock(ProductRepositoryInterface::class);
        $repository->shouldReceive('findBySlug')->with('found-me', true)->andReturn($product);

        $service = $this->makeService($repository);

        $this->assertSame($product, $service->findBySlug('found-me'));
    }

    #[Test]
    public function list_delegates_to_the_repository_with_the_given_filters(): void
    {
        $filters = new ProductFilterData(search: 'tea');
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $repository = Mockery::mock(ProductRepositoryInterface::class);
        $repository->shouldReceive('paginate')
            ->once()
            ->with($filters, true, Mockery::type('array'))
            ->andReturn($paginator);

        $service = $this->makeService($repository);

        $this->assertSame($paginator, $service->list($filters));
    }

    #[Test]
    public function creating_a_published_product_sets_published_at(): void
    {
        /** @var MockInterface&ProductRepositoryInterface $repository */
        $repository = Mockery::mock(ProductRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $attrs) => $attrs['status'] === ProductStatus::Published && $attrs['published_at'] !== null))
            ->andReturn(Product::factory()->make());

        $service = $this->makeService($repository);

        $service->create(new ProductData(name: 'New Product', status: ProductStatus::Published));
    }

    #[Test]
    public function creating_a_draft_product_leaves_published_at_null(): void
    {
        $repository = Mockery::mock(ProductRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $attrs) => $attrs['status'] === ProductStatus::Draft && $attrs['published_at'] === null))
            ->andReturn(Product::factory()->make());

        $service = $this->makeService($repository);

        $service->create(new ProductData(name: 'New Product', status: ProductStatus::Draft));
    }
}
