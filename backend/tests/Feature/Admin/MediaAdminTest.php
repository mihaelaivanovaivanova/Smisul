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

class MediaAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_customer_cannot_list_media(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson('/api/v1/admin/media')->assertForbidden();
    }

    #[Test]
    public function a_guest_is_unauthenticated_on_media_endpoints(): void
    {
        $this->getJson('/api/v1/admin/media')->assertUnauthorized();
    }

    #[Test]
    public function an_administrator_can_list_media_across_every_model(): void
    {
        $admin = User::factory()->administrator()->create();
        Media::factory()->create(['mediable_type' => Product::class, 'mediable_id' => Product::factory()]);
        Media::factory()->create(['mediable_type' => Category::class, 'mediable_id' => Category::factory()]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/media');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function media_can_be_filtered_by_mediable_type(): void
    {
        $admin = User::factory()->administrator()->create();
        Media::factory()->create(['mediable_type' => Product::class, 'mediable_id' => Product::factory()]);
        Media::factory()->create(['mediable_type' => Category::class, 'mediable_id' => Category::factory()]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/media?type=category');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.mediable_type', 'Category');
    }

    #[Test]
    public function media_can_be_searched_by_filename(): void
    {
        $admin = User::factory()->administrator()->create();
        $target = Media::factory()->create(['filename' => 'findme.jpg']);
        Media::factory()->create(['filename' => 'other.jpg']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/media?search=findme');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $target->id);
    }

    #[Test]
    public function an_administrator_can_replace_a_media_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $media = Media::factory()->create(['disk' => 'public', 'path' => 'products/old.jpg']);
        Storage::disk('public')->put('products/old.jpg', 'old-content');

        $response = $this->actingAs($admin)->post("/api/v1/admin/media/{$media->id}", [
            'file' => UploadedFile::fake()->image('new.jpg'),
        ]);

        $response->assertOk();
        Storage::disk('public')->assertMissing('products/old.jpg');
        $this->assertNotSame('products/old.jpg', $media->fresh()->path);
    }

    #[Test]
    public function an_administrator_can_delete_media(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $media = Media::factory()->create(['disk' => 'public', 'path' => 'products/deleteme.jpg']);
        Storage::disk('public')->put('products/deleteme.jpg', 'content');

        $this->actingAs($admin)->deleteJson("/api/v1/admin/media/{$media->id}")->assertNoContent();

        $this->assertModelMissing($media);
        Storage::disk('public')->assertMissing('products/deleteme.jpg');
    }
}
