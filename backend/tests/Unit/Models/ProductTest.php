<?php

namespace Tests\Unit\Models;

use App\Enums\ProductStatus;
use App\Models\Product;
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
}
