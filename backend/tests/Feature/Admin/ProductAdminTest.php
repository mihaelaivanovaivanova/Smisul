<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_guest_cannot_access_the_admin_products_endpoint(): void
    {
        $this->getJson('/api/v1/admin/products')->assertUnauthorized();
    }

    #[Test]
    public function a_customer_cannot_access_the_admin_products_endpoint(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson('/api/v1/admin/products')->assertForbidden();
    }

    #[Test]
    public function an_administrator_can_list_products_including_drafts(): void
    {
        $admin = User::factory()->administrator()->create();
        Product::factory()->create(['status' => ProductStatus::Draft]);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/products')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function an_administrator_can_create_a_product(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/products', [
            'name' => 'New Product',
            'short_description' => 'A short description',
            'status' => 'published',
            'category_ids' => [$category->id],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'New Product');
        $response->assertJsonPath('data.slug', 'new-product');
        $this->assertDatabaseHas('products', ['name' => 'New Product']);
    }

    #[Test]
    public function creating_a_product_requires_a_name(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/products', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function an_administrator_can_update_a_product(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/products/{$product->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New Name');
    }

    #[Test]
    public function publishing_a_product_for_the_first_time_sets_published_at(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create(['status' => ProductStatus::Draft, 'published_at' => null]);

        $this->actingAs($admin)->putJson("/api/v1/admin/products/{$product->id}", [
            'name' => $product->name,
            'status' => 'published',
        ])->assertOk();

        $this->assertNotNull($product->fresh()->published_at);
    }

    #[Test]
    public function an_administrator_can_delete_a_product(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/v1/admin/products/{$product->id}")->assertNoContent();

        $this->assertSoftDeleted($product);
    }

    #[Test]
    public function a_customer_cannot_create_a_product(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->postJson('/api/v1/admin/products', ['name' => 'Nope'])
            ->assertForbidden();
    }
}
