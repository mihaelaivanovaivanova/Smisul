<?php

namespace Tests\Feature\Cart;

use App\Enums\Currency;
use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticatedCartTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableVariant(int $stock = 10): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => $stock]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 9.5]);

        return $variant;
    }

    #[Test]
    public function an_authenticated_user_gets_an_empty_cart_with_no_guest_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/cart');

        $response->assertOk();
        $response->assertJsonPath('data.items', []);
        $this->assertNull($response->json('meta.guest_token'));
    }

    #[Test]
    public function an_authenticated_users_cart_persists_across_separate_requests(): void
    {
        $user = User::factory()->create();
        $variant = $this->purchasableVariant();

        $this->actingAs($user)->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertCreated();

        // A brand-new request cycle (e.g. a second device/session) — same
        // authenticated user, no cart headers at all — still sees the cart.
        $response = $this->actingAs($user)->getJson('/api/v1/cart');

        $response->assertOk();
        $response->assertJsonPath('data.items.0.quantity', 2);
    }

    #[Test]
    public function two_different_users_never_see_each_others_carts(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $variant = $this->purchasableVariant();

        $addResponse = $this->actingAs($userA)->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
        $itemId = $addResponse->json('data.items.0.id');

        $this->actingAs($userB)->getJson('/api/v1/cart')
            ->assertJsonPath('data.items', []);

        $this->actingAs($userB)
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 5])
            ->assertNotFound();
    }

    #[Test]
    public function an_authenticated_user_can_update_and_remove_their_own_items(): void
    {
        $user = User::factory()->create();
        $variant = $this->purchasableVariant();

        $add = $this->actingAs($user)->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
        $itemId = $add->json('data.items.0.id');

        $this->actingAs($user)
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 3])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 3);

        $this->actingAs($user)
            ->deleteJson("/api/v1/cart/items/{$itemId}")
            ->assertOk()
            ->assertJsonPath('data.items', []);
    }
}
