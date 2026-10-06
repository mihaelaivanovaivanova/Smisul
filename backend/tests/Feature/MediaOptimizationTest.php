<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers MediaService::storeOptimized() - uploads were previously stored
 * at whatever resolution/format they arrived in (often multi-MB PNGs),
 * which is what made images slow to load on the live site.
 */
class MediaOptimizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_oversized_image_is_resized_and_converted_to_webp(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $media = app(MediaService::class)->attach(
            $product,
            UploadedFile::fake()->image('front.png', 2000, 2500),
        );

        $this->assertSame('image/webp', $media->mime_type);
        $this->assertStringEndsWith('.webp', $media->path);

        [$width, $height] = getimagesize(Storage::disk('public')->path($media->path));
        $this->assertSame(1600, $width);
        $this->assertSame(2000, $height);
    }

    #[Test]
    public function an_image_already_under_the_max_width_is_not_upscaled(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $media = app(MediaService::class)->attach(
            $product,
            UploadedFile::fake()->image('front.png', 400, 300),
        );

        [$width, $height] = getimagesize(Storage::disk('public')->path($media->path));
        $this->assertSame(400, $width);
        $this->assertSame(300, $height);
    }

    #[Test]
    public function a_video_upload_is_stored_untouched(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $media = app(MediaService::class)->attach(
            $product,
            UploadedFile::fake()->create('demo.mp4', 500, 'video/mp4'),
        );

        $this->assertSame('video/mp4', $media->mime_type);
        $this->assertStringEndsWith('.mp4', $media->path);
    }

    #[Test]
    public function replacing_a_photo_also_optimizes_the_new_file(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $service = app(MediaService::class);

        $media = $service->attach($product, UploadedFile::fake()->image('front.png', 400, 300));
        $replaced = $service->replace($media, UploadedFile::fake()->image('new-front.png', 2000, 1000));

        $this->assertSame('image/webp', $replaced->mime_type);
        [$width] = getimagesize(Storage::disk('public')->path($replaced->path));
        $this->assertSame(1600, $width);
    }
}
