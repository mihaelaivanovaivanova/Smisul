<?php

namespace Tests\Feature\Products;

use App\Enums\Currency;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Promotion;
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
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 19.99]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Smisul Original');
        $response->assertJsonPath('data.variants.0.sku', 'SMISUL-1');
        $response->assertJsonPath('data.variants.0.prices.0.amount', 19.99);
        $response->assertJsonPath('data.variants.0.inventory.is_in_stock', true);
    }

    #[Test]
    public function it_includes_media_mime_type_and_currently_valid_promotions(): void
    {
        $product = Product::factory()->published()->create();
        $product->media()->create([
            'disk' => 'public',
            'path' => 'products/photo.jpg',
            'filename' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
        ]);
        $activePromotion = Promotion::factory()->create(['is_active' => true, 'name' => 'Active Sale']);
        $expiredPromotion = Promotion::factory()->expired()->create(['name' => 'Old Sale']);
        $product->promotions()->attach([$activePromotion->id, $expiredPromotion->id]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.media.0.mime_type', 'image/jpeg');
        $response->assertJsonCount(1, 'data.active_promotions');
        $response->assertJsonPath('data.active_promotions.0.name', 'Active Sale');
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
