<?php

namespace Tests\Feature\Cart;

use App\Enums\Currency;
use App\Enums\VariantStatus;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CartMergeTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableVariant(int $stock = 10): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => $stock]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 4.0]);

        return $variant;
    }

    #[Test]
    public function logging_in_merges_the_guest_cart_into_the_users_cart(): void
    {
        $variantA = $this->purchasableVariant();
        $variantB = $this->purchasableVariant();
        $user = User::factory()->create();

        $add = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variantA->id, 'quantity' => 2]);
        $guestToken = $add->json('meta.guest_token');
        $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->postJson('/api/v1/cart/items', ['product_variant_id' => $variantB->id, 'quantity' => 1]);

        // The frontend keeps sending the stored guest token on the first
        // authenticated request after login — this is what triggers the merge.
        $response = $this->actingAs($user)
            ->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->getJson('/api/v1/cart');

        $response->assertOk();
        $response->assertJsonCount(2, 'data.items');
        $this->assertNull($response->json('meta.guest_token'));

        $quantities = collect($response->json('data.items'))
            ->pluck('quantity', 'product_variant.id');
        $this->assertSame(2, $quantities[$variantA->id]);
        $this->assertSame(1, $quantities[$variantB->id]);

        $this->assertDatabaseCount('carts', 1); // the guest cart was deleted
    }

    #[Test]
    public function merging_sums_quantities_for_a_variant_present_in_both_carts(): void
    {
        $variant = $this->purchasableVariant(stock: 50);
        $user = User::factory()->create();

        // Built while genuinely unauthenticated — actingAs() below persists
        // for the rest of the test, so any "guest" call must happen first.
        $add = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 2]);
        $guestToken = $add->json('meta.guest_token');

        // User already has 3 of this variant in their account cart from a
        // separate, earlier authenticated session (no guest token involved).
        $this->actingAs($user)->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ]);

        // Now they log in on the device that still has the guest cart.
        $response = $this->actingAs($user)
            ->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->getJson('/api/v1/cart');

        $response->assertJsonCount(1, 'data.items'); // no duplicate line
        $response->assertJsonPath('data.items.0.quantity', 5); // 3 + 2, summed
    }

    #[Test]
    public function merging_caps_the_summed_quantity_at_the_absolute_per_item_ceiling_without_dropping_the_line(): void
    {
        // Stock is generous (200) so both adds independently succeed and
        // each genuinely reserves its own quantity in real time — the only
        // thing that can still trim a merge is the absolute 99-per-item
        // ceiling, not availability (that's now enforced at add time).
        $variant = $this->purchasableVariant(stock: 200);
        $user = User::factory()->create();

        $add = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 60]);
        $guestToken = $add->json('meta.guest_token');

        $this->actingAs($user)->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 50,
        ]);

        $response = $this->actingAs($user)
            ->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->getJson('/api/v1/cart');

        // 60 + 50 = 110 requested, capped at the absolute ceiling of 99 —
        // capped, not rejected/lost.
        $response->assertJsonCount(1, 'data.items');
        $response->assertJsonPath('data.items.0.quantity', 99);

        // The 11 units trimmed by the cap are released back to inventory
        // rather than staying reserved for a line that no longer exists.
        $available = $variant->fresh(['inventory'])->inventory->availableQuantity();
        $this->assertSame(200 - 99, $available);
    }

    #[Test]
    public function two_guests_competing_for_the_last_unit_cannot_both_succeed(): void
    {
        $variant = $this->purchasableVariant(stock: 1);

        $first = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $first->assertCreated();

        // A second, independent guest (no token — a fresh cart) tries for
        // the same, now-fully-reserved unit.
        $second = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $second->assertStatus(422);
    }

    #[Test]
    public function merge_is_safe_to_run_again_with_an_already_consumed_guest_token(): void
    {
        $variant = $this->purchasableVariant();
        $user = User::factory()->create();

        $add = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $add->json('meta.guest_token');

        $this->actingAs($user)
            ->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->getJson('/api/v1/cart');

        // The frontend forgot to clear the old token and sends it again.
        // The guest cart behind it was already deleted by the first merge,
        // and an authenticated request never mints a new guest cart (only
        // an unauthenticated one does) — so this is a no-op, and the user's
        // cart is left exactly as it was.
        $response = $this->actingAs($user)
            ->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->getJson('/api/v1/cart');

        $response->assertJsonCount(1, 'data.items');
        $response->assertJsonPath('data.items.0.quantity', 1);
        $this->assertDatabaseCount('carts', 1); // just the user's cart
    }

    #[Test]
    public function a_guest_token_belonging_to_nobody_does_not_error_on_login(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders(['X-Guest-Cart-Token' => (string) Str::uuid()])
            ->getJson('/api/v1/cart');

        $response->assertOk();
        $response->assertJsonPath('data.items', []);
    }

    #[Test]
    public function merging_does_not_touch_a_different_users_cart(): void
    {
        $variant = $this->purchasableVariant();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $cartB = Cart::factory()->forUser($userB)->create();

        $add = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $add->json('meta.guest_token');

        $this->actingAs($userA)
            ->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->getJson('/api/v1/cart');

        $this->assertSame(0, $cartB->fresh(['items'])->items->count());
    }
}
