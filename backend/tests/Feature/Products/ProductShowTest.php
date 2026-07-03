<?php

namespace Tests\Feature\Products;

use App\Enums\Currency;
use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductShowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shows_a_published_product_by_slug_with_variants_and_prices(): void
    {
        $product = Product::factory()->published()->create(['name' => 'Smisul Original']);
        $variant = $product->variants()->create([
            'sku' => 'SMISUL-1',
            'name' => '1-pack',
            'pack_size' => 1,
            'is_default' => true,
        ]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $variant->prices()->create(['currency' => Currency::BGN->value, 'amount' => 19.99]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Smisul Original');
        $response->assertJsonPath('data.variants.0.sku', 'SMISUL-1');
        $response->assertJsonPath('data.variants.0.prices.0.amount', 19.99);
        $response->assertJsonPath('data.variants.0.inventory.is_in_stock', true);
    }

    #[Test]
    public function it_returns_404_for_an_unknown_slug(): void
    {
        $this->getJson('/api/v1/products/does-not-exist')->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_a_draft_product_on_the_public_endpoint(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Draft]);

        $this->getJson("/api/v1/products/{$product->slug}")->assertNotFound();
    }

    #[Test]
    public function it_lists_a_products_variants(): void
    {
        $product = Product::factory()->published()->create();
        $product->variants()->create(['sku' => 'A', 'name' => '1-pack', 'pack_size' => 1]);
        $product->variants()->create(['sku' => 'B', 'name' => '3-pack', 'pack_size' => 3]);

        $response = $this->getJson("/api/v1/products/{$product->slug}/variants");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }
}
