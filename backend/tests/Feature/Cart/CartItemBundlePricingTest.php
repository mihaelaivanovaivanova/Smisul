<?php

namespace Tests\Feature\Cart;

use App\Enums\Currency;
use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers CartItemResource's "was" price for multi-piece pack variants —
 * the cart's own, API-level view of the same non-fabricated "vs. buying
 * singly" savings the funnel/product pages already show via
 * funnelOffers.ts::computeOriginalPrice (see CartPricingService::
 * bundleCompareAtUnitPrice). A real Price.compare_at_amount sale always
 * wins over this derived fallback.
 */
class CartItemBundlePricingTest extends TestCase
{
    use RefreshDatabase;

    private function variant(Product $product, int $packSize, float $amount, VariantStatus $status = VariantStatus::Active): ProductVariant
    {
        $variant = ProductVariant::factory()->for($product)->packSize($packSize)->create(['status' => $status]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => $amount]);

        return $variant;
    }

    #[Test]
    public function a_multi_pack_lines_compare_price_is_the_live_single_pack_price_times_pack_size(): void
    {
        $product = Product::factory()->published()->create();
        $this->variant($product, 1, 5.00);
        $fivePack = $this->variant($product, 5, 20.00);

        $response = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $fivePack->id,
            'quantity' => 1,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.items.0.unit_price', 20);
        $response->assertJsonPath('data.items.0.compare_at_unit_price', 25);
        $response->assertJsonPath('data.items.0.is_on_sale', true);
    }

    #[Test]
    public function a_single_piece_lines_compare_price_is_null(): void
    {
        $product = Product::factory()->published()->create();
        $single = $this->variant($product, 1, 5.00);

        $response = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $single->id,
            'quantity' => 1,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.items.0.compare_at_unit_price', null);
        $response->assertJsonPath('data.items.0.is_on_sale', false);
    }

    #[Test]
    public function a_multi_pack_line_with_no_single_pack_sibling_has_no_compare_price(): void
    {
        $product = Product::factory()->published()->create();
        $fivePack = $this->variant($product, 5, 20.00);

        $response = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $fivePack->id,
            'quantity' => 1,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.items.0.compare_at_unit_price', null);
        $response->assertJsonPath('data.items.0.is_on_sale', false);
    }

    #[Test]
    public function a_real_sale_price_wins_over_the_derived_bundle_comparison(): void
    {
        $product = Product::factory()->published()->create();
        $this->variant($product, 1, 5.00);
        $fivePack = $this->variant($product, 5, 20.00);
        // Real, deliberate markdown on this exact line — should take
        // priority over the derived 25.00 bundle "was" price.
        $fivePack->prices()->first()->update(['compare_at_amount' => 22.00]);

        $response = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $fivePack->id,
            'quantity' => 1,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.items.0.compare_at_unit_price', 22);
        $response->assertJsonPath('data.items.0.is_on_sale', true);
    }
}
