<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Console\Command;

/**
 * One-off backfill for media uploaded before MediaService::attach()/
 * replace() started resizing+converting to WebP on upload - run once
 * after deploying that change (locally and again on the live site via
 * SSH) to shrink the existing multi-MB product photos already on disk.
 * Safe to re-run: reoptimize() is a no-op for anything already WebP.
 */
class OptimizeExistingMedia extends Command
{
    protected $signature = 'media:optimize';

    protected $description = 'Resize and convert to WebP every already-stored image that predates the upload pipeline doing this automatically';

    public function handle(MediaService $service): int
    {
        $images = Media::query()->where('mime_type', 'like', 'image/%')->where('mime_type', '!=', 'image/webp')->get();

        if ($images->isEmpty()) {
            $this->info('Nothing to optimize - every image is already WebP.');

            return self::SUCCESS;
        }

        $this->info("Optimizing {$images->count()} image(s)...");
        $bar = $this->output->createProgressBar($images->count());
        $savedBytes = 0;

        foreach ($images as $media) {
            $before = (int) $media->size;
            $service->reoptimize($media);
            $savedBytes += max(0, $before - (int) $media->size);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Saved '.round($savedBytes / 1_000_000, 1).'MB across '.$images->count().' image(s).');

        return self::SUCCESS;
    }
}
