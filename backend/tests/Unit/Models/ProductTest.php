<?php

namespace Tests\Unit\Models;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_reports_published_status_correctly(): void
    {
        $published = Product::factory()->published()->make();
        $draft = Product::factory()->make(['status' => ProductStatus::Draft]);
        $archived = Product::factory()->archived()->make();

        $this->assertTrue($published->isPublished());
        $this->assertFalse($draft->isPublished());
        $this->assertFalse($archived->isPublished());
    }

    #[Test]
    public function it_generates_a_unique_slug_from_its_name(): void
    {
        $product = Product::factory()->create(['name' => 'Smisul Deluxe']);

        $this->assertSame('smisul-deluxe', $product->slug);
    }

    #[Test]
    public function it_resolves_a_default_variant(): void
    {
        $product = Product::factory()->create();
        $product->variants()->create([
            'sku' => 'SKU-A',
            'name' => '1-pack',
            'pack_size' => 1,
            'is_default' => false,
        ]);
        $defaultVariant = $product->variants()->create([
            'sku' => 'SKU-B',
            'name' => '3-pack',
            'pack_size' => 3,
            'is_default' => true,
        ]);

        $this->assertTrue($product->defaultVariant()->first()->is($defaultVariant));
    }

    #[Test]
    public function active_promotions_includes_promotions_scoped_directly_to_the_product(): void
    {
        $product = Product::factory()->create();
        $promotion = Promotion::factory()->create(['is_active' => true]);
        $product->promotions()->attach($promotion);

        $product->load('categories.promotions', 'promotions');

        $this->assertTrue($product->activePromotions()->contains($promotion));
    }

    #[Test]
    public function active_promotions_includes_promotions_scoped_via_a_category(): void
    {
        $product = Product::factory()->create();
        $category = Category::factory()->create();
        $product->categories()->attach($category);
        $promotion = Promotion::factory()->create(['is_active' => true]);
        $category->promotions()->attach($promotion);

        $product->load('categories.promotions', 'promotions');

        $this->assertTrue($product->activePromotions()->contains($promotion));
    }

    #[Test]
    public function active_promotions_excludes_inactive_or_expired_promotions(): void
    {
        $product = Product::factory()->create();
        $inactive = Promotion::factory()->inactive()->create();
        $expired = Promotion::factory()->expired()->create();
        $product->promotions()->attach([$inactive->id, $expired->id]);

        $product->load('categories.promotions', 'promotions');

        $this->assertCount(0, $product->activePromotions());
    }

    #[Test]
    public function active_promotions_does_not_duplicate_a_promotion_scoped_both_ways(): void
    {
        $product = Product::factory()->create();
        $category = Category::factory()->create();
        $product->categories()->attach($category);
        $promotion = Promotion::factory()->create(['is_active' => true]);
        $product->promotions()->attach($promotion);
        $category->promotions()->attach($promotion);

        $product->load('categories.promotions', 'promotions');

        $this->assertCount(1, $product->activePromotions());
    }
}
