<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductMediaAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_administrator_can_upload_a_photo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/products/{$product->id}/media", [
            'file' => UploadedFile::fake()->image('front.jpg'),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('media', ['mediable_id' => $product->id, 'mediable_type' => Product::class]);
    }

    #[Test]
    public function an_administrator_can_upload_a_video(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/products/{$product->id}/media", [
            'file' => UploadedFile::fake()->create('demo.mp4', 500, 'video/mp4'),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('media', ['mediable_id' => $product->id, 'mime_type' => 'video/mp4']);
    }

    #[Test]
    public function an_unsupported_file_type_is_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)->postJson("/api/v1/admin/products/{$product->id}/media", [
            'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
        ])->assertUnprocessable();
    }

    #[Test]
    public function a_customer_cannot_upload_product_media(): void
    {
        Storage::fake('public');
        $customer = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($customer)->postJson("/api/v1/admin/products/{$product->id}/media", [
            'file' => UploadedFile::fake()->image('front.jpg'),
        ])->assertForbidden();
    }

    #[Test]
    public function uploading_with_is_primary_unsets_the_previous_primary(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $existing = Media::factory()->primary()->create(['mediable_type' => Product::class, 'mediable_id' => $product->id]);

        $this->actingAs($admin)->postJson("/api/v1/admin/products/{$product->id}/media", [
            'file' => UploadedFile::fake()->image('new-primary.jpg'),
            'is_primary' => true,
        ])->assertCreated();

        $this->assertFalse($existing->fresh()->is_primary);
    }

    #[Test]
    public function an_administrator_can_make_an_existing_photo_the_primary_one(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $current = Media::factory()->primary()->create(['mediable_type' => Product::class, 'mediable_id' => $product->id]);
        $other = Media::factory()->create(['mediable_type' => Product::class, 'mediable_id' => $product->id]);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/products/{$product->id}/media/{$other->id}/primary");

        $response->assertOk();
        $response->assertJsonPath('data.is_primary', true);
        $this->assertFalse($current->fresh()->is_primary);
        $this->assertTrue($other->fresh()->is_primary);
    }

    #[Test]
    public function an_administrator_can_delete_product_media(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $media = Media::factory()->create(['mediable_type' => Product::class, 'mediable_id' => $product->id]);

        $this->actingAs($admin)->deleteJson("/api/v1/admin/products/{$product->id}/media/{$media->id}")
            ->assertNoContent();

        $this->assertModelMissing($media);
    }

    #[Test]
    public function media_belonging_to_a_different_product_cannot_be_deleted_or_promoted_through_this_product(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();
        $mediaOfOtherProduct = Media::factory()->create(['mediable_type' => Product::class, 'mediable_id' => $otherProduct->id]);

        $this->actingAs($admin)->deleteJson("/api/v1/admin/products/{$product->id}/media/{$mediaOfOtherProduct->id}")
            ->assertNotFound();

        $this->actingAs($admin)->patchJson("/api/v1/admin/products/{$product->id}/media/{$mediaOfOtherProduct->id}/primary")
            ->assertNotFound();

        $this->assertModelExists($mediaOfOtherProduct);
    }

    #[Test]
    public function media_belonging_to_a_category_cannot_be_touched_through_a_product(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $categoryMedia = Media::factory()->create(['mediable_type' => Category::class, 'mediable_id' => Category::factory()]);

        $this->actingAs($admin)->deleteJson("/api/v1/admin/products/{$product->id}/media/{$categoryMedia->id}")
            ->assertNotFound();
    }
}
