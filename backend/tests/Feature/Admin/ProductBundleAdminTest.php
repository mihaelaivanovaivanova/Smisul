<?php

namespace Tests\Feature\Admin;

use App\Enums\Currency;
use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductBundleAdminTest extends TestCase
{
    use RefreshDatabase;

    private function variant(float $amount = 5.0): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => 20]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => $amount]);

        return $variant;
    }

    #[Test]
    public function an_administrator_can_create_a_kit_from_existing_variants(): void
    {
        $admin = User::factory()->administrator()->create();
        $a = $this->variant(10);
        $b = $this->variant(4);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/bundles', [
            'slug' => 'new-kit',
            'name' => 'New Kit',
            'amount' => 15.5,
            'items' => [
                ['product_variant_id' => $a->id, 'quantity' => 1],
                ['product_variant_id' => $b->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.slug', 'new-kit');
        $response->assertJsonPath('data.amount', 15.5);
        $response->assertJsonPath('data.retail_value', 18);
        $response->assertJsonCount(2, 'data.items');
    }

    #[Test]
    public function a_kit_cannot_list_the_same_variant_twice(): void
    {
        $admin = User::factory()->administrator()->create();
        $a = $this->variant();

        $this->actingAs($admin)->postJson('/api/v1/admin/bundles', [
            'slug' => 'dup-kit',
            'name' => 'Dup',
            'amount' => 10,
            'items' => [
                ['product_variant_id' => $a->id, 'quantity' => 1],
                ['product_variant_id' => $a->id, 'quantity' => 1],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.product_variant_id');
    }

    #[Test]
    public function a_customer_cannot_manage_kits(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/v1/admin/bundles')->assertForbidden();
    }

    #[Test]
    public function a_kits_components_cannot_change_while_a_cart_holds_it_but_its_price_can(): void
    {
        $admin = User::factory()->administrator()->create();
        $a = $this->variant();
        $b = $this->variant();
        $bundle = ProductBundle::create(['slug' => 'held-kit', 'name' => 'Held', 'currency' => 'EUR', 'amount' => 8, 'status' => 'active']);
        $bundle->items()->create(['product_variant_id' => $a->id, 'quantity' => 1]);

        $this->postJson('/api/v1/cart/bundles', ['bundle_id' => $bundle->id])->assertCreated();

        $this->actingAs($admin)->putJson("/api/v1/admin/bundles/{$bundle->id}", [
            'items' => [['product_variant_id' => $b->id, 'quantity' => 1]],
        ])->assertUnprocessable();

        $this->actingAs($admin)->deleteJson("/api/v1/admin/bundles/{$bundle->id}")->assertUnprocessable();

        $this->actingAs($admin)->putJson("/api/v1/admin/bundles/{$bundle->id}", ['amount' => 7.5])
            ->assertOk()
            ->assertJsonPath('data.amount', 7.5);

        $this->assertSame($a->id, $bundle->items()->sole()->product_variant_id);
    }

    #[Test]
    public function an_unused_kit_can_be_deleted(): void
    {
        $admin = User::factory()->administrator()->create();
        $bundle = ProductBundle::create(['slug' => 'gone-kit', 'name' => 'Gone', 'currency' => 'EUR', 'amount' => 8, 'status' => 'active']);
        $bundle->items()->create(['product_variant_id' => $this->variant()->id, 'quantity' => 1]);

        $this->actingAs($admin)->deleteJson("/api/v1/admin/bundles/{$bundle->id}")->assertNoContent();

        $this->assertNull(ProductBundle::find($bundle->id));
        $this->assertDatabaseCount('product_bundle_items', 0);
    }
}
