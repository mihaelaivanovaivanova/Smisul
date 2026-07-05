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
    public function creating_a_product_with_quantity_and_price_creates_a_default_variant(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/products', [
            'name' => 'Herbal Tea',
            'quantity' => 25,
            'price' => 12.50,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.quantity', 25);
        $response->assertJsonPath('data.price', 12.5);

        $product = Product::query()->where('name', 'Herbal Tea')->firstOrFail();
        $variant = $product->variants()->where('is_default', true)->firstOrFail();
        $this->assertSame(25, $variant->inventory->quantity_on_hand);
        $this->assertSame('12.50', (string) $variant->prices()->where('currency', 'EUR')->firstOrFail()->amount);
    }

    #[Test]
    public function creating_a_product_without_quantity_or_price_creates_no_variant(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/products', ['name' => 'No Stock Yet']);

        $response->assertCreated();
        $response->assertJsonPath('data.quantity', null);
        $response->assertJsonPath('data.price', null);

        $product = Product::query()->where('name', 'No Stock Yet')->firstOrFail();
        $this->assertSame(0, $product->variants()->count());
    }

    #[Test]
    public function updating_quantity_and_price_on_a_product_with_an_existing_default_variant_updates_it_in_place(): void
    {
        $admin = User::factory()->administrator()->create();
        $create = $this->actingAs($admin)->postJson('/api/v1/admin/products', [
            'name' => 'Restockable', 'quantity' => 5, 'price' => 9.99,
        ]);
        $productId = $create->json('data.id');

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/products/{$productId}", [
            'name' => 'Restockable', 'quantity' => 40, 'price' => 11.50,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.quantity', 40);
        $response->assertJsonPath('data.price', 11.5);

        $product = Product::query()->findOrFail($productId);
        $this->assertSame(1, $product->variants()->count());
    }

    #[Test]
    public function setting_quantity_on_a_product_created_before_this_feature_creates_its_default_variant_on_the_fly(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create(['name' => 'Legacy Product']);

        $this->actingAs($admin)->putJson("/api/v1/admin/products/{$product->id}", [
            'name' => 'Legacy Product', 'quantity' => 10,
        ])->assertOk()->assertJsonPath('data.quantity', 10);

        $this->assertSame(1, $product->variants()->count());
    }

    #[Test]
    public function a_negative_quantity_is_rejected(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/products', [
            'name' => 'Bad Quantity', 'quantity' => -1,
        ])->assertUnprocessable();
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
