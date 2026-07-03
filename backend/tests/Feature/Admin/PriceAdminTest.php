<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PriceAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_administrator_can_set_a_variants_price(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $variant = $product->variants()->create(['sku' => 'SKU-1', 'name' => '1-pack', 'pack_size' => 1]);

        $response = $this->actingAs($admin)->putJson(
            "/api/v1/admin/products/{$product->id}/variants/{$variant->id}/price",
            ['currency' => 'BGN', 'amount' => 24.99],
        );

        $response->assertOk();
        $response->assertJsonPath('data.amount', 24.99);
        $this->assertDatabaseHas('price_histories', [
            'product_variant_id' => $variant->id,
            'new_amount' => 24.99,
        ]);
    }

    #[Test]
    public function compare_at_amount_must_be_greater_than_amount(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $variant = $product->variants()->create(['sku' => 'SKU-1', 'name' => '1-pack', 'pack_size' => 1]);

        $this->actingAs($admin)->putJson(
            "/api/v1/admin/products/{$product->id}/variants/{$variant->id}/price",
            ['currency' => 'BGN', 'amount' => 20, 'compare_at_amount' => 10],
        )->assertUnprocessable()->assertJsonValidationErrors('compare_at_amount');
    }

    #[Test]
    public function a_customer_cannot_set_a_price(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $variant = $product->variants()->create(['sku' => 'SKU-1', 'name' => '1-pack', 'pack_size' => 1]);

        $this->actingAs($customer)->putJson(
            "/api/v1/admin/products/{$product->id}/variants/{$variant->id}/price",
            ['currency' => 'BGN', 'amount' => 24.99],
        )->assertForbidden();
    }
}
