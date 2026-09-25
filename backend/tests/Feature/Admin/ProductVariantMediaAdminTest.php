<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductVariantMediaAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_administrator_can_upload_a_photo_for_a_variant(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/admin/products/{$product->id}/variants/{$variant->id}/media",
            ['file' => UploadedFile::fake()->image('pack-5.jpg')],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.is_primary', true);
        $this->assertDatabaseHas('media', ['mediable_type' => ProductVariant::class, 'mediable_id' => $variant->id]);
    }

    #[Test]
    public function uploading_again_replaces_the_variants_existing_photo_in_place(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $existing = Media::factory()->create(['mediable_type' => ProductVariant::class, 'mediable_id' => $variant->id]);

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/admin/products/{$product->id}/variants/{$variant->id}/media",
            ['file' => UploadedFile::fake()->image('replacement.jpg')],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.id', $existing->id);
        $this->assertSame(1, Media::where('mediable_type', ProductVariant::class)->where('mediable_id', $variant->id)->count());
    }

    #[Test]
    public function a_video_is_rejected_for_a_variant_photo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();

        $this->actingAs($admin)->postJson(
            "/api/v1/admin/products/{$product->id}/variants/{$variant->id}/media",
            ['file' => UploadedFile::fake()->create('demo.mp4', 500, 'video/mp4')],
        )->assertUnprocessable();
    }

    #[Test]
    public function a_customer_cannot_upload_a_variant_photo(): void
    {
        Storage::fake('public');
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();

        $this->actingAs($customer)->postJson(
            "/api/v1/admin/products/{$product->id}/variants/{$variant->id}/media",
            ['file' => UploadedFile::fake()->image('pack-5.jpg')],
        )->assertForbidden();
    }

    #[Test]
    public function an_administrator_can_clear_a_variants_photo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $media = Media::factory()->create(['mediable_type' => ProductVariant::class, 'mediable_id' => $variant->id]);

        $this->actingAs($admin)->deleteJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}/media")
            ->assertNoContent();

        $this->assertModelMissing($media);
    }

    #[Test]
    public function an_administrator_can_set_a_focus_point_on_a_variants_photo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $media = Media::factory()->create(['mediable_type' => ProductVariant::class, 'mediable_id' => $variant->id]);

        $response = $this->actingAs($admin)->patchJson(
            "/api/v1/admin/products/{$product->id}/variants/{$variant->id}/media/{$media->id}/focus",
            ['focus_x' => 0.2, 'focus_y' => 0.8],
        );

        $response->assertOk();
        $response->assertJsonPath('data.focus_x', 0.2);
        $response->assertJsonPath('data.focus_y', 0.8);
    }

    #[Test]
    public function a_variants_focus_point_cannot_be_set_through_a_different_variant(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $otherVariant = ProductVariant::factory()->for($product)->create();
        $media = Media::factory()->create(['mediable_type' => ProductVariant::class, 'mediable_id' => $variant->id]);

        $this->actingAs($admin)->patchJson(
            "/api/v1/admin/products/{$product->id}/variants/{$otherVariant->id}/media/{$media->id}/focus",
            ['focus_x' => 0.2, 'focus_y' => 0.8],
        )->assertNotFound();
    }
}
