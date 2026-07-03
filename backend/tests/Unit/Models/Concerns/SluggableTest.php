<?php

namespace Tests\Unit\Models\Concerns;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SluggableTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_generates_a_slug_from_the_name_on_create(): void
    {
        $category = Category::factory()->create(['name' => 'Herbal Teas']);

        $this->assertSame('herbal-teas', $category->slug);
    }

    #[Test]
    public function it_does_not_overwrite_an_explicitly_provided_slug(): void
    {
        $category = Category::factory()->create(['name' => 'Herbal Teas', 'slug' => 'custom-slug']);

        $this->assertSame('custom-slug', $category->slug);
    }

    #[Test]
    public function it_resolves_collisions_by_appending_a_number(): void
    {
        Category::factory()->create(['name' => 'Teas']);
        $second = Category::factory()->create(['name' => 'Teas']);
        $third = Category::factory()->create(['name' => 'Teas']);

        $this->assertSame('teas-2', $second->slug);
        $this->assertSame('teas-3', $third->slug);
    }

    #[Test]
    public function a_soft_deleted_record_still_reserves_its_slug(): void
    {
        $original = Category::factory()->create(['name' => 'Teas']);
        $original->delete();

        $newOne = Category::factory()->create(['name' => 'Teas']);

        $this->assertSame('teas-2', $newOne->slug);
    }

    #[Test]
    public function updating_the_name_does_not_regenerate_the_slug(): void
    {
        $category = Category::factory()->create(['name' => 'Teas']);
        $category->update(['name' => 'Renamed Teas']);

        $this->assertSame('teas', $category->fresh()->slug);
    }
}
