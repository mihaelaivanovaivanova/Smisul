<?php

namespace Tests\Feature\Cart;

use App\Enums\Currency;
use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CartValidationTest extends TestCase
{
    use RefreshDatabase;

    private function variant(array $overrides = []): ProductVariant
    {
        $product = Product::factory()->published()->create();

        return ProductVariant::factory()->for($product)->create(array_merge(['status' => VariantStatus::Active], $overrides));
    }

    #[Test]
    public function adding_a_variant_of_an_unpublished_product_is_rejected(): void
    {
        $product = Product::factory()->create(); // draft by default
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertStatus(422);
    }

    #[Test]
    public function adding_an_inactive_variant_is_rejected(): void
    {
        $variant = $this->variant(['status' => VariantStatus::Inactive]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertStatus(422);
    }

    #[Test]
    public function adding_a_soft_deleted_variant_is_rejected_by_validation(): void
    {
        $variant = $this->variant();
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $variant->delete();

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('product_variant_id');
    }

    #[Test]
    public function quantity_below_the_minimum_is_rejected(): void
    {
        $variant = $this->variant();
        $variant->inventory()->create(['quantity_on_hand' => 10]);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('quantity');
    }

    #[Test]
    public function quantity_above_the_absolute_ceiling_is_rejected(): void
    {
        $variant = $this->variant();
        $variant->inventory()->create(['quantity_on_hand' => 1000, 'backorders_allowed' => true]);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => CartPricingService::MAX_QUANTITY_PER_ITEM + 1,
        ])->assertStatus(422)->assertJsonValidationErrors('quantity');
    }

    #[Test]
    public function adding_more_than_available_stock_is_rejected(): void
    {
        $variant = $this->variant();
        $variant->inventory()->create(['quantity_on_hand' => 3]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 1]);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 4,
        ])->assertStatus(422);
    }

    #[Test]
    public function backorders_allowed_permits_adding_more_than_on_hand_stock(): void
    {
        $variant = $this->variant();
        $variant->inventory()->create(['quantity_on_hand' => 1, 'backorders_allowed' => true]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 1]);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 10,
        ])->assertCreated();
    }

    #[Test]
    public function updating_an_item_beyond_available_stock_is_rejected(): void
    {
        $variant = $this->variant();
        $variant->inventory()->create(['quantity_on_hand' => 5]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 1]);

        $add = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 2]);
        $token = $add->json('meta.guest_token');
        $itemId = $add->json('data.items.0.id');

        $this->withHeaders(['X-Guest-Cart-Token' => $token])
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 6])
            ->assertStatus(422);
    }

    #[Test]
    public function a_line_is_flagged_unavailable_when_stock_drops_below_the_cart_quantity_after_adding(): void
    {
        $variant = $this->variant();
        $inventory = $variant->inventory()->create(['quantity_on_hand' => 5]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 1]);

        $add = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 5]);
        $token = $add->json('meta.guest_token');

        // Stock drops after the item was already in the cart (e.g. another
        // customer bought the remaining units) — simulate that directly.
        $inventory->update(['quantity_on_hand' => 1]);

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $token])->getJson('/api/v1/cart');

        $response->assertOk();
        $response->assertJsonPath('data.items.0.quantity', 5); // never silently changed
        $response->assertJsonPath('data.items.0.is_available', false);
    }

    #[Test]
    public function adding_a_nonexistent_variant_is_rejected(): void
    {
        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => 999999,
            'quantity' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('product_variant_id');
    }
}
