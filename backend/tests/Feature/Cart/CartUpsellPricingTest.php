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
 * Covers the bamboo-case cross-sell's upsell_amount — only ever charged
 * when added with is_upsell=true AND the cart already contains a real
 * Miswak item (CartService::isEligibleForUpsellPrice()), never just
 * because the client asked for it. See CartPricingService::effectivePrice().
 */
class CartUpsellPricingTest extends TestCase
{
    use RefreshDatabase;

    private function variant(string $productSlug, string $sku, float $amount, ?float $upsellAmount = null): ProductVariant
    {
        $product = Product::factory()->published()->create(['slug' => $productSlug]);
        $variant = ProductVariant::factory()->for($product)->create(['sku' => $sku, 'status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => $amount, 'upsell_amount' => $upsellAmount]);

        return $variant;
    }

    #[Test]
    public function the_case_is_charged_at_its_upsell_price_when_miswak_is_already_in_the_cart(): void
    {
        $miswak = $this->variant('miswak', 'MISWAK-1', 4.29);
        $case = $this->variant('bambukov-keis-za-miswak', 'MISWAK-CASE-1', 6.49, 5.49);

        $addMiswak = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $miswak->id, 'quantity' => 1]);
        $addMiswak->assertCreated();
        $guestToken = $addMiswak->json('meta.guest_token');

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/cart/items', [
            'product_variant_id' => $case->id,
            'quantity' => 1,
            'is_upsell' => true,
        ]);

        $response->assertCreated();
        $caseLine = collect($response->json('data.items'))->firstWhere('product_variant.id', $case->id);
        $this->assertSame(5.49, $caseLine['unit_price']);
        $this->assertSame(6.49, $caseLine['compare_at_unit_price']);
        $this->assertTrue($caseLine['is_on_sale']);
        $this->assertSame(5.49, $caseLine['line_total']);
    }

    #[Test]
    public function the_upsell_flag_is_ignored_without_miswak_in_the_cart(): void
    {
        $case = $this->variant('bambukov-keis-za-miswak', 'MISWAK-CASE-1', 6.49, 5.49);

        $response = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $case->id,
            'quantity' => 1,
            'is_upsell' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.items.0.unit_price', 6.49);
        $response->assertJsonPath('data.items.0.is_on_sale', false);
    }

    #[Test]
    public function the_upsell_flag_only_applies_to_the_bamboo_case_variant(): void
    {
        $miswak = $this->variant('miswak', 'MISWAK-1', 4.29);
        $otherProduct = $this->variant('some-other-product', 'OTHER-1', 10.00, 8.00);

        $addMiswak = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $miswak->id, 'quantity' => 1]);
        $addMiswak->assertCreated();
        $guestToken = $addMiswak->json('meta.guest_token');

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/cart/items', [
            'product_variant_id' => $otherProduct->id,
            'quantity' => 1,
            'is_upsell' => true,
        ]);

        $response->assertCreated();
        $otherLine = collect($response->json('data.items'))->firstWhere('product_variant.id', $otherProduct->id);
        $this->assertEquals(10, $otherLine['unit_price']);
        $this->assertFalse($otherLine['is_on_sale']);
    }

    #[Test]
    public function adding_the_case_without_the_upsell_flag_charges_the_regular_price_even_with_miswak_in_cart(): void
    {
        $miswak = $this->variant('miswak', 'MISWAK-1', 4.29);
        $case = $this->variant('bambukov-keis-za-miswak', 'MISWAK-CASE-1', 6.49, 5.49);

        $addMiswak = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $miswak->id, 'quantity' => 1]);
        $addMiswak->assertCreated();
        $guestToken = $addMiswak->json('meta.guest_token');

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/cart/items', [
            'product_variant_id' => $case->id,
            'quantity' => 1,
        ]);

        $response->assertCreated();
        $caseLine = collect($response->json('data.items'))->firstWhere('product_variant.id', $case->id);
        $this->assertSame(6.49, $caseLine['unit_price']);
        $this->assertFalse($caseLine['is_on_sale']);
    }

    #[Test]
    public function the_upsell_price_keeps_applying_after_a_quantity_change(): void
    {
        $miswak = $this->variant('miswak', 'MISWAK-1', 4.29);
        $case = $this->variant('bambukov-keis-za-miswak', 'MISWAK-CASE-1', 6.49, 5.49);

        $addMiswak = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $miswak->id, 'quantity' => 1]);
        $addMiswak->assertCreated();
        $guestToken = $addMiswak->json('meta.guest_token');

        $addResponse = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/cart/items', [
            'product_variant_id' => $case->id,
            'quantity' => 1,
            'is_upsell' => true,
        ]);
        $itemId = collect($addResponse->json('data.items'))->firstWhere('product_variant.id', $case->id)['id'];

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 3]);

        $response->assertOk();
        $caseLine = collect($response->json('data.items'))->firstWhere('product_variant.id', $case->id);
        $this->assertSame(5.49, $caseLine['unit_price']);
        $this->assertSame(16.47, $caseLine['line_total']);
    }

    #[Test]
    public function a_real_sale_price_wins_over_the_upsell_price(): void
    {
        $miswak = $this->variant('miswak', 'MISWAK-1', 4.29);
        $case = $this->variant('bambukov-keis-za-miswak', 'MISWAK-CASE-1', 6.49, 5.49);
        $case->prices()->first()->update(['compare_at_amount' => 7.99]);

        $addMiswak = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $miswak->id, 'quantity' => 1]);
        $addMiswak->assertCreated();
        $guestToken = $addMiswak->json('meta.guest_token');

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/cart/items', [
            'product_variant_id' => $case->id,
            'quantity' => 1,
            'is_upsell' => true,
        ]);

        $response->assertCreated();
        $caseLine = collect($response->json('data.items'))->firstWhere('product_variant.id', $case->id);
        $this->assertSame(6.49, $caseLine['unit_price']);
        $this->assertSame(7.99, $caseLine['compare_at_unit_price']);
    }

    #[Test]
    public function the_offer_endpoint_shows_the_upsell_once_miswak_is_in_the_cart(): void
    {
        $miswak = $this->variant('miswak', 'MISWAK-1', 4.29);
        $case = $this->variant('bambukov-keis-za-miswak', 'MISWAK-CASE-1', 6.49, 5.49);

        $addMiswak = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $miswak->id, 'quantity' => 1]);
        $addMiswak->assertCreated();
        $guestToken = $addMiswak->json('meta.guest_token');

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->getJson('/api/v1/cart/upsell');

        $response->assertOk();
        $response->assertJsonPath('data.variant_id', $case->id);
        $response->assertJsonPath('data.amount', 5.49);
        $response->assertJsonPath('data.compare_at_amount', 6.49);
        $response->assertJsonPath('data.product.slug', 'bambukov-keis-za-miswak');
    }

    #[Test]
    public function the_offer_endpoint_is_null_without_miswak_in_the_cart(): void
    {
        $this->variant('bambukov-keis-za-miswak', 'MISWAK-CASE-1', 6.49, 5.49);

        $this->getJson('/api/v1/cart/upsell')->assertOk()->assertJsonPath('data', null);
    }

    #[Test]
    public function the_offer_endpoint_is_null_once_the_case_is_already_in_the_cart(): void
    {
        $miswak = $this->variant('miswak', 'MISWAK-1', 4.29);
        $case = $this->variant('bambukov-keis-za-miswak', 'MISWAK-CASE-1', 6.49, 5.49);

        $addMiswak = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $miswak->id, 'quantity' => 1]);
        $addMiswak->assertCreated();
        $guestToken = $addMiswak->json('meta.guest_token');

        $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->postJson('/api/v1/cart/items', ['product_variant_id' => $case->id, 'quantity' => 1])
            ->assertCreated();

        $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->getJson('/api/v1/cart/upsell')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    #[Test]
    public function the_offer_endpoint_is_null_when_no_upsell_amount_is_configured(): void
    {
        $miswak = $this->variant('miswak', 'MISWAK-1', 4.29);
        $this->variant('bambukov-keis-za-miswak', 'MISWAK-CASE-1', 6.49, null);

        $addMiswak = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $miswak->id, 'quantity' => 1]);
        $addMiswak->assertCreated();
        $guestToken = $addMiswak->json('meta.guest_token');

        $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->getJson('/api/v1/cart/upsell')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    /**
     * Regression test for a real bug: is_upsell was only ever set once, at
     * add time, and never re-checked — so removing the Miswak item that
     * justified the discount left the case charging the upsell price
     * forever. See CartService::revokeUpsellPriceIfMiswakGone().
     */
    #[Test]
    public function removing_the_last_miswak_item_reverts_the_case_to_its_regular_price(): void
    {
        $miswak = $this->variant('miswak', 'MISWAK-1', 4.29);
        $case = $this->variant('bambukov-keis-za-miswak', 'MISWAK-CASE-1', 6.49, 5.49);

        $addMiswak = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $miswak->id, 'quantity' => 1]);
        $addMiswak->assertCreated();
        $guestToken = $addMiswak->json('meta.guest_token');
        $miswakItemId = collect($addMiswak->json('data.items'))->firstWhere('product_variant.id', $miswak->id)['id'];

        $addCase = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/cart/items', [
            'product_variant_id' => $case->id,
            'quantity' => 1,
            'is_upsell' => true,
        ]);
        $addCase->assertCreated();
        $addedCaseLine = collect($addCase->json('data.items'))->firstWhere('product_variant.id', $case->id);
        $this->assertSame(5.49, $addedCaseLine['unit_price']);

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->deleteJson("/api/v1/cart/items/{$miswakItemId}");

        $response->assertOk();
        $caseLine = collect($response->json('data.items'))->firstWhere('product_variant.id', $case->id);
        $this->assertSame(6.49, $caseLine['unit_price']);
        $this->assertFalse($caseLine['is_on_sale']);
        $this->assertDatabaseHas('cart_items', ['product_variant_id' => $case->id, 'is_upsell' => false]);
    }

    #[Test]
    public function removing_one_of_two_miswak_variants_keeps_the_cases_upsell_price(): void
    {
        $miswak1 = $this->variant('miswak', 'MISWAK-1', 4.29);
        $miswak5 = ProductVariant::factory()->for($miswak1->product)->create(['sku' => 'MISWAK-5', 'status' => VariantStatus::Active]);
        $miswak5->inventory()->create(['quantity_on_hand' => 10]);
        $miswak5->prices()->create(['currency' => Currency::EUR->value, 'amount' => 17.99]);
        $case = $this->variant('bambukov-keis-za-miswak', 'MISWAK-CASE-1', 6.49, 5.49);

        $addMiswak1 = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $miswak1->id, 'quantity' => 1]);
        $addMiswak1->assertCreated();
        $guestToken = $addMiswak1->json('meta.guest_token');
        $miswak1ItemId = collect($addMiswak1->json('data.items'))->firstWhere('product_variant.id', $miswak1->id)['id'];

        $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->postJson('/api/v1/cart/items', ['product_variant_id' => $miswak5->id, 'quantity' => 1])
            ->assertCreated();

        $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/cart/items', [
            'product_variant_id' => $case->id,
            'quantity' => 1,
            'is_upsell' => true,
        ])->assertCreated();

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->deleteJson("/api/v1/cart/items/{$miswak1ItemId}");

        $response->assertOk();
        $caseLine = collect($response->json('data.items'))->firstWhere('product_variant.id', $case->id);
        $this->assertSame(5.49, $caseLine['unit_price']);
        $this->assertTrue($caseLine['is_on_sale']);
    }
}
