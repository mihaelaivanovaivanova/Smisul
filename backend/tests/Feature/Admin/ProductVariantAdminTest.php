<?php

namespace Tests\Feature\Admin;

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
}
