<?php

namespace Tests\Unit\Services;

use App\DataTransferObjects\CategoryData;
use App\Exceptions\CategoryNotFoundException;
use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Services\CategoryService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryServiceTest extends TestCase
{
    #[Test]
    public function find_by_slug_throws_a_domain_exception_when_not_found(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $repository->shouldReceive('findBySlug')->with('missing', true)->andReturnNull();

        $service = new CategoryService($repository);

        $this->expectException(CategoryNotFoundException::class);

        $service->findBySlug('missing');
    }

    #[Test]
    public function tree_delegates_to_the_repository(): void
    {
        $collection = new Collection([Category::factory()->make()]);

        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $repository->shouldReceive('tree')->once()->with(true)->andReturn($collection);

        $service = new CategoryService($repository);

        $this->assertSame($collection, $service->tree());
    }

    #[Test]
    public function create_maps_dto_fields_onto_repository_attributes(): void
    {
        $data = new CategoryData(name: 'Teas', parentId: 4, description: 'All teas', isActive: false, sortOrder: 2);

        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->with([
                'parent_id' => 4,
                'name' => 'Teas',
                'description' => 'All teas',
                'is_active' => false,
                'sort_order' => 2,
            ])
            ->andReturn(Category::factory()->make());

        $service = new CategoryService($repository);

        $service->create($data);
    }
}
