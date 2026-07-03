<?php

namespace Tests\Feature\Cart;

use App\Enums\Currency;
use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GuestCartTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableVariant(int $stock = 10): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => $stock]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 19.99]);

        return $variant;
    }

    #[Test]
    public function a_guest_gets_an_empty_cart_with_a_fresh_token_on_first_visit(): void
    {
        $response = $this->getJson('/api/v1/cart');

        $response->assertOk();
        $response->assertJsonPath('data.items', []);
        $response->assertJsonPath('data.total_quantity', 0);
        $this->assertNotNull($response->json('meta.guest_token'));
    }

    #[Test]
    public function a_guest_rejects_a_malformed_cart_token(): void
    {
        $this->withHeaders(['X-Guest-Cart-Token' => 'not-a-uuid'])
            ->getJson('/api/v1/cart')
            ->assertStatus(422);
    }

    #[Test]
    public function a_guest_can_add_an_item_and_receives_a_reusable_token(): void
    {
        $variant = $this->purchasableVariant();

        $response = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.items.0.quantity', 2);
        $response->assertJsonPath('data.items.0.unit_price', 19.99);
        $response->assertJsonPath('data.items.0.line_total', 39.98);
        $response->assertJsonPath('data.totals.subtotal', 39.98);
        $response->assertJsonPath('data.totals.grand_total', 39.98);
        $this->assertNotNull($response->json('meta.guest_token'));
    }

    #[Test]
    public function the_same_token_returns_the_same_cart_across_requests(): void
    {
        $variant = $this->purchasableVariant();

        $first = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
        $token = $first->json('meta.guest_token');

        $second = $this->withHeaders(['X-Guest-Cart-Token' => $token])->getJson('/api/v1/cart');

        $second->assertOk();
        $second->assertJsonPath('data.id', $first->json('data.id'));
        $second->assertJsonPath('data.items.0.quantity', 1);
    }

    #[Test]
    public function adding_the_same_variant_twice_increments_quantity_instead_of_duplicating_the_line(): void
    {
        $variant = $this->purchasableVariant();

        $first = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);
        $token = $first->json('meta.guest_token');

        $second = $this->withHeaders(['X-Guest-Cart-Token' => $token])->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ]);

        $second->assertOk(); // 200, not 201 — no new line was created.
        $second->assertJsonCount(1, 'data.items');
        $second->assertJsonPath('data.items.0.quantity', 5);
    }

    #[Test]
    public function a_guest_can_update_an_items_quantity(): void
    {
        $variant = $this->purchasableVariant();
        $add = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $token = $add->json('meta.guest_token');
        $itemId = $add->json('data.items.0.id');

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $token])
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 4]);

        $response->assertOk();
        $response->assertJsonPath('data.items.0.quantity', 4);
    }

    #[Test]
    public function a_guest_can_remove_an_item(): void
    {
        $variant = $this->purchasableVariant();
        $add = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $token = $add->json('meta.guest_token');
        $itemId = $add->json('data.items.0.id');

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $token])
            ->deleteJson("/api/v1/cart/items/{$itemId}");

        $response->assertOk();
        $response->assertJsonPath('data.items', []);
    }

    #[Test]
    public function a_guest_can_clear_the_entire_cart(): void
    {
        $variantA = $this->purchasableVariant();
        $variantB = $this->purchasableVariant();
        $add = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variantA->id, 'quantity' => 1]);
        $token = $add->json('meta.guest_token');
        $this->withHeaders(['X-Guest-Cart-Token' => $token])
            ->postJson('/api/v1/cart/items', ['product_variant_id' => $variantB->id, 'quantity' => 1]);

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $token])->deleteJson('/api/v1/cart');

        $response->assertOk();
        $response->assertJsonPath('data.items', []);
        $response->assertJsonPath('data.total_quantity', 0);
    }

    #[Test]
    public function two_different_guest_tokens_never_see_each_others_carts(): void
    {
        $variant = $this->purchasableVariant();

        $cartA = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $tokenA = $cartA->json('meta.guest_token');
        $itemIdA = $cartA->json('data.items.0.id');

        $cartB = $this->getJson('/api/v1/cart'); // no token -> a brand new, different guest cart
        $tokenB = $cartB->json('meta.guest_token');

        $this->assertNotSame($tokenA, $tokenB);

        $this->withHeaders(['X-Guest-Cart-Token' => $tokenB])
            ->patchJson("/api/v1/cart/items/{$itemIdA}", ['quantity' => 9])
            ->assertNotFound();
    }
}
