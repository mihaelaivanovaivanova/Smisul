<?php

namespace Tests\Feature\Repositories;

use App\Models\Category;
use App\Repositories\EloquentCategoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EloquentCategoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentCategoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentCategoryRepository;
    }

    #[Test]
    public function find_by_slug_respects_the_active_only_flag(): void
    {
        Category::factory()->inactive()->create(['slug' => 'hidden']);

        $this->assertNull($this->repository->findBySlug('hidden'));
        $this->assertNotNull($this->repository->findBySlug('hidden', activeOnly: false));
    }

    #[Test]
    public function tree_returns_only_root_categories_with_children_nested(): void
    {
        $root = Category::factory()->create(['name' => 'Root']);
        $child = Category::factory()->childOf($root)->create(['name' => 'Child']);
        Category::factory()->childOf($root)->inactive()->create(['name' => 'Hidden Child']);

        $tree = $this->repository->tree(activeOnly: true);

        $this->assertCount(1, $tree);
        $this->assertTrue($tree->first()->is($root));
        $this->assertCount(1, $tree->first()->children);
        $this->assertTrue($tree->first()->children->first()->is($child));
    }

    #[Test]
    public function tree_excludes_inactive_root_categories_when_restricted(): void
    {
        Category::factory()->inactive()->create(['name' => 'Hidden Root']);
        $visible = Category::factory()->create(['name' => 'Visible Root']);

        $tree = $this->repository->tree(activeOnly: true);

        $this->assertCount(1, $tree);
        $this->assertTrue($tree->first()->is($visible));
    }

    #[Test]
    public function delete_soft_deletes_the_category(): void
    {
        $category = Category::factory()->create();

        $this->repository->delete($category);

        $this->assertSoftDeleted($category);
    }
}
