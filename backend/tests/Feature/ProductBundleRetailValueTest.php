<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ProductBundle::retailValue() - the kit's "you save" strike-through basis.
 * The one thing worth a dedicated test: a multi-piece pack component's
 * TRUE separate-purchase price is its 1-piece sibling's price x pack size,
 * not the pack's own (already bulk-discounted) listed price.
 */
class ProductBundleRetailValueTest extends TestCase
{
    use RefreshDatabase;

    private function priced(ProductVariant $variant, float $amount): ProductVariant
    {
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => $amount]);

        return $variant;
    }

    #[Test]
    public function a_pack_components_retail_value_uses_its_single_piece_siblings_price_not_its_own(): void
    {
        $product = Product::factory()->published()->create();
        $single = $this->priced(
            ProductVariant::factory()->for($product)->packSize(1)->create(['status' => VariantStatus::Active]),
            4.43,
        );
        $pack = $this->priced(
            ProductVariant::factory()->for($product)->packSize(5)->create(['status' => VariantStatus::Active]),
            17.99, // the pack's own bulk-discounted price - NOT what retailValue() should use
        );

        $bundle = ProductBundle::create([
            'slug' => 'retail-value-test-kit',
            'name' => 'Test Kit',
            'currency' => Currency::EUR->value,
            'amount' => 20,
            'status' => VariantStatus::Active->value,
        ]);
        $bundle->items()->create(['product_variant_id' => $pack->id, 'quantity' => 1]);
        $bundle->load(ProductBundle::COMPONENT_EAGER_LOAD);

        // 5 x 4.43 (the true single-piece price), not 1 x 17.99 (the pack's own price).
        $this->assertSame(22.15, $bundle->retailValue('EUR'));

        $single->delete();
        $bundle->unsetRelation('items');
        $bundle->load(ProductBundle::COMPONENT_EAGER_LOAD);

        // No 1-piece sibling left: falls back to the pack's own price.
        $this->assertSame(17.99, $bundle->retailValue('EUR'));
    }
}
