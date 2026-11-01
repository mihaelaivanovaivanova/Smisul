<?php

namespace Tests\Feature\Cart;

use App\Enums\Currency;
use App\Enums\VariantStatus;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kits (ProductBundle) in the cart: one cart line at one stored price,
 * with every stock operation applied to each real component variant,
 * scaled by that component's per-kit quantity - see BundleService and
 * CartService's bundle branches.
 */
class CartBundleStockTest extends TestCase
{
    use RefreshDatabase;

    private function variant(float $amount, int $stock, int $packSize = 1): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->packSize($packSize)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => $stock]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => $amount]);

        return $variant;
    }

    /**
     * A 2-Person-Kit-shaped bundle: 1x pack + 2x case + 2x scraper.
     *
     * @return array{0: ProductBundle, 1: ProductVariant, 2: ProductVariant, 3: ProductVariant}
     */
    private function kit(int $packStock = 50, int $caseStock = 50, int $scraperStock = 50, float $amount = 54.99): array
    {
        $pack = $this->variant(34.49, $packStock, 10);
        $case = $this->variant(6.49, $caseStock);
        $scraper = $this->variant(6.99, $scraperStock);

        $bundle = ProductBundle::create([
            'slug' => 'test-2-person-kit',
            'name' => '2-Person Kit',
            'currency' => Currency::EUR->value,
            'amount' => $amount,
            'status' => VariantStatus::Active->value,
        ]);
        $bundle->items()->create(['product_variant_id' => $pack->id, 'quantity' => 1]);
        $bundle->items()->create(['product_variant_id' => $case->id, 'quantity' => 2]);
        $bundle->items()->create(['product_variant_id' => $scraper->id, 'quantity' => 2]);

        return [$bundle, $pack, $case, $scraper];
    }

    private function reserved(ProductVariant $variant): int
    {
        return $variant->inventory()->firstOrFail()->quantity_reserved;
    }

    #[Test]
    public function adding_a_kit_reserves_every_component_scaled_by_its_per_kit_quantity(): void
    {
        [$bundle, $pack, $case, $scraper] = $this->kit();

        $response = $this->postJson('/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 2]);

        $response->assertCreated();
        $response->assertJsonCount(1, 'data.items');
        $response->assertJsonPath('data.items.0.quantity', 2);
        $response->assertJsonPath('data.items.0.product_variant', null);
        $response->assertJsonPath('data.items.0.bundle.slug', 'test-2-person-kit');
        // One stored price; the components' live separate total
        // (34.49 + 2x6.49 + 2x6.99 = 61.45) is only the compare-at.
        $response->assertJsonPath('data.items.0.unit_price', 54.99);
        $response->assertJsonPath('data.items.0.compare_at_unit_price', 61.45);
        $response->assertJsonPath('data.items.0.is_on_sale', true);
        $response->assertJsonPath('data.items.0.line_total', 109.98);
        $response->assertJsonPath('data.totals.subtotal', 109.98);

        $this->assertSame(2, $this->reserved($pack));
        $this->assertSame(4, $this->reserved($case));
        $this->assertSame(4, $this->reserved($scraper));
    }

    #[Test]
    public function adding_a_kit_with_one_component_out_of_stock_reserves_nothing_for_any_component(): void
    {
        [$bundle, $pack, $case, $scraper] = $this->kit(scraperStock: 0);

        $response = $this->postJson('/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 1]);

        $response->assertUnprocessable();
        $this->assertSame(0, $this->reserved($pack));
        $this->assertSame(0, $this->reserved($case));
        $this->assertSame(0, $this->reserved($scraper));
        $this->assertSame(0, CartItem::count());
    }

    #[Test]
    public function a_component_with_some_stock_but_not_enough_for_its_scaled_share_also_blocks_the_whole_kit(): void
    {
        // The kit needs 2 cases per kit; 3 in stock covers 1 kit, not 2.
        [$bundle, $pack, $case, $scraper] = $this->kit(caseStock: 3);

        $this->postJson('/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 2])
            ->assertUnprocessable();

        $this->assertSame(0, $this->reserved($pack));
        $this->assertSame(0, $this->reserved($case));
        $this->assertSame(0, $this->reserved($scraper));
    }

    #[Test]
    public function adding_the_same_kit_again_increments_its_single_line(): void
    {
        [$bundle, $pack, $case] = $this->kit();

        $first = $this->postJson('/api/v1/cart/bundles', ['bundle_id' => $bundle->id]);
        $token = $first->json('meta.guest_token');

        $second = $this->withHeaders(['X-Guest-Cart-Token' => $token])
            ->postJson('/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 2]);

        $second->assertOk();
        $second->assertJsonCount(1, 'data.items');
        $second->assertJsonPath('data.items.0.quantity', 3);
        $this->assertSame(3, $this->reserved($pack));
        $this->assertSame(6, $this->reserved($case));
    }

    #[Test]
    public function a_kit_line_coexists_with_an_ordinary_line_for_one_of_its_own_components(): void
    {
        [$bundle, $pack, $case] = $this->kit();

        $first = $this->postJson('/api/v1/cart/bundles', ['bundle_id' => $bundle->id]);
        $token = $first->json('meta.guest_token');

        $this->withHeaders(['X-Guest-Cart-Token' => $token])
            ->postJson('/api/v1/cart/items', ['product_variant_id' => $case->id, 'quantity' => 1])
            ->assertCreated()
            ->assertJsonCount(2, 'data.items');

        // 2 for the kit + 1 for the standalone line, on the same row.
        $this->assertSame(3, $this->reserved($case));
        $this->assertSame(1, $this->reserved($pack));
    }

    #[Test]
    public function changing_a_kit_lines_quantity_reserves_or_releases_the_delta_across_all_components(): void
    {
        [$bundle, $pack, $case, $scraper] = $this->kit();

        $add = $this->postJson('/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 1]);
        $token = $add->json('meta.guest_token');
        $itemId = $add->json('data.items.0.id');

        $this->withHeaders(['X-Guest-Cart-Token' => $token])
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 4);

        $this->assertSame(4, $this->reserved($pack));
        $this->assertSame(8, $this->reserved($case));
        $this->assertSame(8, $this->reserved($scraper));

        $this->withHeaders(['X-Guest-Cart-Token' => $token])
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 1])
            ->assertOk();

        $this->assertSame(1, $this->reserved($pack));
        $this->assertSame(2, $this->reserved($case));
        $this->assertSame(2, $this->reserved($scraper));
    }

    #[Test]
    public function growing_a_kit_line_beyond_one_components_stock_changes_nothing(): void
    {
        [$bundle, $pack, $case, $scraper] = $this->kit(scraperStock: 4);

        $add = $this->postJson('/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 1]);
        $token = $add->json('meta.guest_token');
        $itemId = $add->json('data.items.0.id');
        // 4 scrapers at 2 per kit caps this line at 2 kits.
        $add->assertJsonPath('data.items.0.max_quantity', 2);

        $this->withHeaders(['X-Guest-Cart-Token' => $token])
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 3])
            ->assertUnprocessable();

        $this->assertSame(1, CartItem::findOrFail($itemId)->quantity);
        $this->assertSame(1, $this->reserved($pack));
        $this->assertSame(2, $this->reserved($case));
        $this->assertSame(2, $this->reserved($scraper));
    }

    #[Test]
    public function removing_a_kit_line_releases_every_components_reservation(): void
    {
        [$bundle, $pack, $case, $scraper] = $this->kit();

        $add = $this->postJson('/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 3]);
        $token = $add->json('meta.guest_token');
        $itemId = $add->json('data.items.0.id');

        $this->withHeaders(['X-Guest-Cart-Token' => $token])
            ->deleteJson("/api/v1/cart/items/{$itemId}")
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->assertSame(0, $this->reserved($pack));
        $this->assertSame(0, $this->reserved($case));
        $this->assertSame(0, $this->reserved($scraper));
    }

    #[Test]
    public function clearing_the_cart_releases_a_kit_lines_components_too(): void
    {
        [$bundle, $pack, $case] = $this->kit();

        $add = $this->postJson('/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 2]);
        $token = $add->json('meta.guest_token');

        $this->withHeaders(['X-Guest-Cart-Token' => $token])->deleteJson('/api/v1/cart')->assertOk();

        $this->assertSame(0, $this->reserved($pack));
        $this->assertSame(0, $this->reserved($case));
    }

    #[Test]
    public function guest_cart_merge_keeps_the_kit_line_and_sums_into_an_existing_one(): void
    {
        [$bundle, $pack, $case] = $this->kit();
        $user = User::factory()->create();

        $add = $this->postJson('/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 2]);
        $guestToken = $add->json('meta.guest_token');

        $this->actingAs($user)->postJson('/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 1]);

        $response = $this->actingAs($user)
            ->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->getJson('/api/v1/cart');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.items');
        $response->assertJsonPath('data.items.0.bundle.id', $bundle->id);
        $response->assertJsonPath('data.items.0.quantity', 3);

        // Merging never changes the total held when nothing is capped.
        $this->assertSame(3, $this->reserved($pack));
        $this->assertSame(6, $this->reserved($case));
        $this->assertDatabaseCount('carts', 1);
    }

    #[Test]
    public function guest_cart_merge_caps_a_kit_line_and_releases_the_excess_across_all_components(): void
    {
        [$bundle, $pack, $case, $scraper] = $this->kit(packStock: 300, caseStock: 300, scraperStock: 300);
        $user = User::factory()->create();

        $add = $this->postJson('/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 60]);
        $guestToken = $add->json('meta.guest_token');

        $this->actingAs($user)->postJson('/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 50]);

        $response = $this->actingAs($user)
            ->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->getJson('/api/v1/cart');

        // 60 + 50 = 110 kits, capped at 99; the 11 trimmed kits' worth of
        // every component is released, not left reserved for nothing.
        $response->assertJsonCount(1, 'data.items');
        $response->assertJsonPath('data.items.0.quantity', 99);
        $this->assertSame(99, $this->reserved($pack));
        $this->assertSame(198, $this->reserved($case));
        $this->assertSame(198, $this->reserved($scraper));
    }

    #[Test]
    public function a_kit_with_an_unpublished_component_cannot_be_added(): void
    {
        [$bundle, $pack] = $this->kit();
        $pack->update(['status' => VariantStatus::Inactive]);

        $this->postJson('/api/v1/cart/bundles', ['bundle_id' => $bundle->id])->assertUnprocessable();
        $this->assertSame(0, CartItem::count());
    }

    #[Test]
    public function the_public_bundle_list_shows_only_active_kits_with_their_live_retail_value(): void
    {
        [$bundle] = $this->kit();
        ProductBundle::create([
            'slug' => 'retired-kit',
            'name' => 'Retired',
            'currency' => Currency::EUR->value,
            'amount' => 10,
            'status' => VariantStatus::Inactive->value,
        ]);

        $response = $this->getJson('/api/v1/bundles');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.slug', $bundle->slug);
        $response->assertJsonPath('data.0.amount', 54.99);
        $response->assertJsonPath('data.0.retail_value', 61.45);
        $response->assertJsonPath('data.0.savings', 6.46);
        $response->assertJsonPath('data.0.is_available', true);
        $response->assertJsonCount(3, 'data.0.items');
    }
}
