<?php

namespace Tests\Feature\Admin;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductVariantAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_administrator_can_add_a_variant_to_a_product(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/products/{$product->id}/variants", [
            'sku' => 'SKU-1',
            'name' => '1-pack',
            'pack_size' => 1,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('product_variants', ['sku' => 'SKU-1', 'product_id' => $product->id]);
        $this->assertDatabaseHas('inventories', ['quantity_on_hand' => 0]);
    }

    #[Test]
    public function it_rejects_a_duplicate_sku(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $product->variants()->create(['sku' => 'DUPLICATE', 'name' => '1-pack', 'pack_size' => 1]);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/products/{$product->id}/variants", [
                'sku' => 'DUPLICATE',
                'name' => '3-pack',
                'pack_size' => 3,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sku');
    }

    #[Test]
    public function a_customer_cannot_add_a_variant(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($customer)
            ->postJson("/api/v1/admin/products/{$product->id}/variants", [
                'sku' => 'SKU-1',
                'name' => '1-pack',
                'pack_size' => 1,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function an_administrator_can_delete_a_variant(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $variant = $product->variants()->create(['sku' => 'SKU-1', 'name' => '1-pack', 'pack_size' => 1]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($variant);
    }

    #[Test]
    public function an_administrator_can_set_a_variants_stock(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $variant = $product->variants()->create(['sku' => 'SKU-1', 'name' => '1-pack', 'pack_size' => 1]);
        $variant->inventory()->create(['quantity_on_hand' => 0]);

        $response = $this->actingAs($admin)->putJson(
            "/api/v1/admin/products/{$product->id}/variants/{$variant->id}/inventory",
            ['quantity_on_hand' => 50],
        );

        $response->assertOk();
        $response->assertJsonPath('data.available_quantity', 50);
        $this->assertDatabaseHas('inventories', ['product_variant_id' => $variant->id, 'quantity_on_hand' => 50]);
    }

    #[Test]
    public function setting_stock_requires_a_non_negative_integer(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $variant = $product->variants()->create(['sku' => 'SKU-1', 'name' => '1-pack', 'pack_size' => 1]);
        $variant->inventory()->create(['quantity_on_hand' => 0]);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}/inventory", ['quantity_on_hand' => -5])
            ->assertUnprocessable();
    }

    #[Test]
    public function a_customer_cannot_set_a_variants_stock(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $variant = $product->variants()->create(['sku' => 'SKU-1', 'name' => '1-pack', 'pack_size' => 1]);
        $variant->inventory()->create(['quantity_on_hand' => 0]);

        $this->actingAs($customer)
            ->putJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}/inventory", ['quantity_on_hand' => 10])
            ->assertForbidden();
    }

    /**
     * Regression coverage for a real production bug: available_quantity
     * (on_hand - reserved) read 0 while quantity_on_hand was genuinely 4,
     * because stale cart reservations were eating all of it - and nothing
     * in the admin UI could tell the two numbers apart. See Admin\
     * InventoryResource and InventoryService::reservationsFor().
     */
    #[Test]
    public function an_administrator_can_see_the_real_on_hand_count_behind_available_quantity(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $variant = $product->variants()->create(['sku' => 'SKU-1', 'name' => '1-pack', 'pack_size' => 1]);
        $variant->inventory()->create(['quantity_on_hand' => 4, 'quantity_reserved' => 4]);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/products/{$product->id}");

        $response->assertOk();
        $response->assertJsonPath('data.variants.0.inventory.available_quantity', 0);
        $response->assertJsonPath('data.variants.0.inventory.quantity_on_hand', 4);
        $response->assertJsonPath('data.variants.0.inventory.quantity_reserved', 4);
    }

    #[Test]
    public function an_administrator_can_list_the_carts_holding_a_variants_reserved_stock(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $variant = $product->variants()->create(['sku' => 'SKU-1', 'name' => '1-pack', 'pack_size' => 1]);
        $variant->inventory()->create(['quantity_on_hand' => 4, 'quantity_reserved' => 4]);

        $customer = User::factory()->create(['email' => 'holder@example.com']);
        $userCart = Cart::factory()->forUser($customer)->create();
        CartItem::factory()->for($userCart)->for($variant, 'productVariant')->create(['quantity' => 1]);

        $guestCart = Cart::factory()->create();
        $guestItem = CartItem::factory()->for($guestCart)->for($variant, 'productVariant')->create(['quantity' => 3]);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}/reservations");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['is_guest' => false, 'customer_email' => 'holder@example.com', 'quantity' => 1]);
        $response->assertJsonFragment(['id' => $guestItem->id, 'is_guest' => true, 'quantity' => 3]);
    }

    #[Test]
    public function an_administrator_can_release_a_stale_reservation(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $variant = $product->variants()->create(['sku' => 'SKU-1', 'name' => '1-pack', 'pack_size' => 1]);
        $variant->inventory()->create(['quantity_on_hand' => 4, 'quantity_reserved' => 4]);

        $cart = Cart::factory()->create();
        $item = CartItem::factory()->for($cart)->for($variant, 'productVariant')->create(['quantity' => 4]);

        $response = $this->actingAs($admin)->deleteJson(
            "/api/v1/admin/products/{$product->id}/variants/{$variant->id}/reservations/{$item->id}",
        );

        $response->assertOk();
        $response->assertJsonPath('data.available_quantity', 4);
        $response->assertJsonPath('data.quantity_reserved', 0);
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
        $this->assertDatabaseHas('inventories', ['product_variant_id' => $variant->id, 'quantity_on_hand' => 4, 'quantity_reserved' => 0]);
        $this->assertDatabaseHas('admin_action_logs', [
            'user_id' => $admin->id,
            'action' => 'inventory.reservation_released',
        ]);
    }

    #[Test]
    public function releasing_a_reservation_for_the_wrong_variant_is_not_found(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $variant = $product->variants()->create(['sku' => 'SKU-1', 'name' => '1-pack', 'pack_size' => 1]);
        $variant->inventory()->create(['quantity_on_hand' => 4, 'quantity_reserved' => 4]);

        $otherVariant = Product::factory()->create()->variants()->create(['sku' => 'SKU-2', 'name' => '1-pack', 'pack_size' => 1]);
        $otherVariant->inventory()->create(['quantity_on_hand' => 1, 'quantity_reserved' => 1]);
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->for($cart)->for($otherVariant, 'productVariant')->create(['quantity' => 1]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}/reservations/{$item->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('cart_items', ['id' => $item->id]);
    }

    #[Test]
    public function a_customer_cannot_view_or_release_reservations(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $variant = $product->variants()->create(['sku' => 'SKU-1', 'name' => '1-pack', 'pack_size' => 1]);
        $variant->inventory()->create(['quantity_on_hand' => 1, 'quantity_reserved' => 1]);
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->for($cart)->for($variant, 'productVariant')->create(['quantity' => 1]);

        $this->actingAs($customer)
            ->getJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}/reservations")
            ->assertForbidden();

        $this->actingAs($customer)
            ->deleteJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}/reservations/{$item->id}")
            ->assertForbidden();
    }
}
