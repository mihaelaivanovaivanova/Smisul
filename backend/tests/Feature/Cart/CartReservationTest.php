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
 * End-to-end coverage for the invariant CartService maintains: a variant's
 * Inventory::quantity_reserved always equals the sum of that variant's
 * quantity across every cart_item in the database. Verified here by
 * reading Inventory::availableQuantity() straight from the model after
 * each API call, independent of what the cart response itself reports.
 */
class CartReservationTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableVariant(int $stock = 10): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => $stock]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 5.0]);

        return $variant;
    }

    #[Test]
    public function adding_an_item_reserves_stock(): void
    {
        $variant = $this->purchasableVariant(stock: 10);

        $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 3])
            ->assertCreated();

        $this->assertSame(7, $variant->fresh(['inventory'])->inventory->availableQuantity());
    }

    #[Test]
    public function incrementing_an_existing_line_reserves_only_the_added_delta(): void
    {
        $variant = $this->purchasableVariant(stock: 10);

        $add = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 3]);
        $token = $add->json('meta.guest_token');

        $this->withHeaders(['X-Guest-Cart-Token' => $token])
            ->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 2])
            ->assertOk();

        // 5 reserved in total (3 + 2), not 3 + (3 + 2).
        $this->assertSame(5, $variant->fresh(['inventory'])->inventory->availableQuantity());
    }

    #[Test]
    public function increasing_quantity_via_patch_reserves_the_delta(): void
    {
        $variant = $this->purchasableVariant(stock: 10);

        $add = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 2]);
        $token = $add->json('meta.guest_token');
        $itemId = $add->json('data.items.0.id');

        $this->withHeaders(['X-Guest-Cart-Token' => $token])
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 6])
            ->assertOk();

        $this->assertSame(4, $variant->fresh(['inventory'])->inventory->availableQuantity());
    }

    #[Test]
    public function decreasing_quantity_via_patch_releases_the_delta(): void
    {
        $variant = $this->purchasableVariant(stock: 10);

        $add = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 6]);
        $token = $add->json('meta.guest_token');
        $itemId = $add->json('data.items.0.id');

        $this->withHeaders(['X-Guest-Cart-Token' => $token])
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 2])
            ->assertOk();

        $this->assertSame(8, $variant->fresh(['inventory'])->inventory->availableQuantity());
    }

    #[Test]
    public function removing_an_item_releases_its_reservation(): void
    {
        $variant = $this->purchasableVariant(stock: 10);

        $add = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 4]);
        $token = $add->json('meta.guest_token');
        $itemId = $add->json('data.items.0.id');

        $this->withHeaders(['X-Guest-Cart-Token' => $token])
            ->deleteJson("/api/v1/cart/items/{$itemId}")
            ->assertOk();

        $this->assertSame(10, $variant->fresh(['inventory'])->inventory->availableQuantity());
    }

    #[Test]
    public function clearing_the_cart_releases_every_reservation(): void
    {
        $variantA = $this->purchasableVariant(stock: 10);
        $variantB = $this->purchasableVariant(stock: 5);

        $add = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variantA->id, 'quantity' => 3]);
        $token = $add->json('meta.guest_token');
        $this->withHeaders(['X-Guest-Cart-Token' => $token])
            ->postJson('/api/v1/cart/items', ['product_variant_id' => $variantB->id, 'quantity' => 2]);

        $this->withHeaders(['X-Guest-Cart-Token' => $token])
            ->deleteJson('/api/v1/cart')
            ->assertOk();

        $this->assertSame(10, $variantA->fresh(['inventory'])->inventory->availableQuantity());
        $this->assertSame(5, $variantB->fresh(['inventory'])->inventory->availableQuantity());
    }

    #[Test]
    public function a_failed_add_beyond_stock_does_not_reserve_anything(): void
    {
        $variant = $this->purchasableVariant(stock: 3);

        $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 4])
            ->assertStatus(422);

        $this->assertSame(3, $variant->fresh(['inventory'])->inventory->availableQuantity());
    }

    #[Test]
    public function a_failed_patch_beyond_stock_does_not_change_the_reservation(): void
    {
        $variant = $this->purchasableVariant(stock: 5);

        $add = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 3]);
        $token = $add->json('meta.guest_token');
        $itemId = $add->json('data.items.0.id');

        $this->withHeaders(['X-Guest-Cart-Token' => $token])
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 10])
            ->assertStatus(422);

        // Still exactly 3 reserved — the failed attempt to grow to 10 left
        // the original reservation untouched.
        $this->assertSame(2, $variant->fresh(['inventory'])->inventory->availableQuantity());
    }

    #[Test]
    public function backorder_items_still_track_a_reservation_count(): void
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => 0, 'backorders_allowed' => true]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 5.0]);

        $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 5])
            ->assertCreated();

        $this->assertSame(5, $variant->fresh(['inventory'])->inventory->quantity_reserved);
    }
}
