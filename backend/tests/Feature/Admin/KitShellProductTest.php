<?php

namespace Tests\Feature\Admin;

use App\Enums\Currency;
use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every kit is backed by a catalog "shell" Product + default variant +
 * price + inventory (see KitShellProductService) - created with the kit,
 * synced on every kit save, never sold through the ordinary cart.
 */
class KitShellProductTest extends TestCase
{
    use RefreshDatabase;

    private function variant(float $amount = 5.0, ?string $sku = null): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create([
            'status' => VariantStatus::Active,
            ...($sku !== null ? ['sku' => $sku] : []),
        ]);
        $variant->inventory()->create(['quantity_on_hand' => 20]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => $amount]);

        return $variant;
    }

    private function createKit(User $admin, array $overrides = []): ProductBundle
    {
        $response = $this->actingAs($admin)->postJson('/api/v1/admin/bundles', [
            'slug' => 'new-kit',
            'name' => 'New Kit',
            'amount' => 15.5,
            'items' => [
                ['product_variant_id' => $this->variant(10)->id, 'quantity' => 1],
            ],
            ...$overrides,
        ]);

        $response->assertCreated();

        return ProductBundle::findOrFail($response->json('data.id'));
    }

    #[Test]
    public function creating_a_kit_creates_its_published_catalog_shell(): void
    {
        $admin = User::factory()->administrator()->create();

        $bundle = $this->createKit($admin);

        $this->assertNotNull($bundle->shell_product_variant_id);
        $shell = ProductVariant::with(['product', 'prices', 'inventory'])->findOrFail($bundle->shell_product_variant_id);

        $this->assertSame('KIT-NEW-KIT-SHELL', $shell->sku);
        $this->assertSame(1, $shell->pack_size);
        $this->assertTrue($shell->is_default);
        $this->assertSame('new-kit', $shell->product->slug);
        $this->assertSame('New Kit', $shell->product->name);
        $this->assertSame(ProductStatus::Published, $shell->product->status);
        $this->assertNotNull($shell->product->published_at);
        $this->assertSame(1, $shell->product->variants()->count());
        $this->assertSame('15.50', $shell->priceFor('EUR')->amount);
        $this->assertSame(9999, $shell->inventory->quantity_on_hand);
        $this->assertTrue($shell->inventory->backorders_allowed);
        $this->assertTrue($shell->inventory->isInStock());

        // Exposed publicly, and the kit lists in the ordinary catalog.
        $this->getJson('/api/v1/bundles')->assertJsonPath('data.0.shell_product_variant_id', $shell->id);
        $this->assertContains('new-kit', collect($this->getJson('/api/v1/products?per_page=50')->json('data'))->pluck('slug')->all());
        $this->getJson('/api/v1/products/new-kit')->assertOk();
    }

    /**
     * The shell being a plain Product means its photos are managed exactly
     * like any other product's (upload/reorder/set-main/delete via
     * /admin/products - see ProductMediaManager.tsx) - BundleResource's own
     * `gallery` field is what carries that media to the storefront's kit
     * selector (see PurchasePanel.tsx/KitProductPage.tsx).
     */
    #[Test]
    public function a_kits_gallery_reflects_its_shells_own_uploaded_photos(): void
    {
        $admin = User::factory()->administrator()->create();
        $bundle = $this->createKit($admin);
        $shellProduct = ProductVariant::findOrFail($bundle->shell_product_variant_id)->product;

        $this->getJson('/api/v1/bundles')->assertJsonPath('data.0.gallery', []);

        $media = $shellProduct->media()->create([
            'disk' => 'public',
            'path' => 'products/test-kit-photo.webp',
            'filename' => 'test-kit-photo.webp',
            'mime_type' => 'image/webp',
            'size' => 1024,
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $this->getJson('/api/v1/bundles')
            ->assertJsonPath('data.0.gallery.0.id', $media->id)
            ->assertJsonPath('data.0.gallery.0.is_primary', true);
    }

    #[Test]
    public function updating_a_kit_syncs_its_shell_without_clobbering_admin_edited_content(): void
    {
        $admin = User::factory()->administrator()->create();
        $bundle = $this->createKit($admin);
        $shell = ProductVariant::findOrFail($bundle->shell_product_variant_id);

        // An admin's own later edit via /admin/products.
        $shell->product->update(['short_description' => 'Admin tagline', 'description' => 'Admin long copy']);

        $this->actingAs($admin)->putJson("/api/v1/admin/bundles/{$bundle->id}", [
            'name' => 'Renamed Kit',
            'amount' => 12.25,
        ])->assertOk();

        $shell->refresh()->load('product');
        $this->assertSame('Renamed Kit', $shell->product->name);
        $this->assertSame('Renamed Kit', $shell->name);
        $this->assertSame('12.25', $shell->priceFor('EUR')->amount);
        $this->assertSame('Admin tagline', $shell->product->short_description);
        $this->assertSame('Admin long copy', $shell->product->description);
        $this->assertSame($shell->id, $bundle->fresh()->shell_product_variant_id);
        $this->assertSame(1, ProductVariant::where('sku', 'KIT-NEW-KIT-SHELL')->count());

        // Deactivating the kit hides the shell from the catalog...
        $this->actingAs($admin)->putJson("/api/v1/admin/bundles/{$bundle->id}", ['status' => 'inactive'])->assertOk();
        $this->assertSame(ProductStatus::Draft, $shell->product->fresh()->status);
        $this->getJson('/api/v1/products/new-kit')->assertNotFound();

        // ...and reactivating republishes it.
        $this->actingAs($admin)->putJson("/api/v1/admin/bundles/{$bundle->id}", ['status' => 'active'])->assertOk();
        $this->assertSame(ProductStatus::Published, $shell->product->fresh()->status);
    }

    #[Test]
    public function a_kits_shell_sku_never_takes_over_a_real_catalog_sku(): void
    {
        $admin = User::factory()->administrator()->create();
        $real = $this->variant(3, 'KIT-NEW-KIT-SHELL');

        $this->actingAs($admin)->postJson('/api/v1/admin/bundles', [
            'slug' => 'new-kit',
            'name' => 'New Kit',
            'amount' => 15.5,
            'items' => [['product_variant_id' => $this->variant()->id, 'quantity' => 1]],
        ])->assertUnprocessable();

        // Rolled back whole - no half-made kit, and the real variant untouched.
        $this->assertNull(ProductBundle::where('slug', 'new-kit')->first());
        $this->assertNull(Product::where('slug', 'new-kit')->first());
        $this->assertSame('3.00', $real->fresh()->priceFor('EUR')->amount);
    }

    #[Test]
    public function a_kit_slug_cannot_take_an_existing_products_slug(): void
    {
        $admin = User::factory()->administrator()->create();
        Product::factory()->published()->create(['slug' => 'taken-slug']);

        $this->actingAs($admin)->postJson('/api/v1/admin/bundles', [
            'slug' => 'taken-slug',
            'name' => 'Kit',
            'amount' => 10,
            'items' => [['product_variant_id' => $this->variant()->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('slug');

        // A kit's own shell doesn't block re-saving its own slug.
        $bundle = $this->createKit($admin);
        $this->actingAs($admin)->putJson("/api/v1/admin/bundles/{$bundle->id}", ['slug' => 'new-kit'])->assertOk();
        $this->actingAs($admin)->putJson("/api/v1/admin/bundles/{$bundle->id}", ['slug' => 'taken-slug'])
            ->assertUnprocessable()->assertJsonValidationErrors('slug');
    }

    #[Test]
    public function a_kits_shell_cannot_be_bought_through_the_ordinary_cart(): void
    {
        $admin = User::factory()->administrator()->create();
        $bundle = $this->createKit($admin);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $bundle->shell_product_variant_id,
            'quantity' => 1,
        ])->assertUnprocessable();
    }

    #[Test]
    public function deleting_a_kit_archives_its_shell(): void
    {
        $admin = User::factory()->administrator()->create();
        $bundle = $this->createKit($admin);
        $shellId = $bundle->shell_product_variant_id;

        $this->actingAs($admin)->deleteJson("/api/v1/admin/bundles/{$bundle->id}")->assertNoContent();

        $shell = ProductVariant::with('product')->findOrFail($shellId);
        $this->assertSame(ProductStatus::Archived, $shell->product->status);
        $this->getJson('/api/v1/products/new-kit')->assertNotFound();
    }
}
