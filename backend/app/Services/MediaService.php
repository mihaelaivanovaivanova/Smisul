<?php

namespace App\Services;

use App\DataTransferObjects\Admin\MediaFilterData;
use App\Models\Contracts\IsMediable;
use App\Models\Media;
use App\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    /**
     * Cross-model listing for the admin Media Library — every Media row
     * regardless of which mediable it's attached to.
     */
    public function list(MediaFilterData $filters): LengthAwarePaginator
    {
        $query = Media::query()->latest();

        if ($filters->search !== null && $filters->search !== '') {
            $query->where('filename', 'like', "%{$filters->search}%");
        }

        if ($filters->mediableType !== null) {
            $query->where('mediable_type', $filters->mediableType);
        }

        if ($filters->mimeType !== null) {
            $query->where('mime_type', 'like', "{$filters->mimeType}%");
        }

        return $query->paginate($filters->perPage, page: $filters->page);
    }

    /**
     * Swaps the underlying file for an existing Media row in place — same
     * id, same mediable association — rather than detach+attach, so
     * anything referencing this Media by id keeps working.
     */
    public function replace(Media $media, UploadedFile $file, ?string $altText = null): Media
    {
        $stored = $this->storeOptimized($file, dirname($media->path), $media->disk);

        Storage::disk($media->disk)->delete($media->path);

        $media->update([
            'path' => $stored['path'],
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $stored['mime_type'],
            'size' => Storage::disk($media->disk)->size($stored['path']),
            'alt_text' => $altText ?? $media->alt_text,
        ]);

        return $media->fresh();
    }

    /**
     * Attach an uploaded file to any "mediable" model. Images are resized
     * and re-encoded by storeOptimized() below; anything else (video, PDF)
     * is stored as uploaded.
     */
    public function attach(
        Model&IsMediable $mediable,
        UploadedFile $file,
        ?string $altText = null,
        bool $isPrimary = false,
        string $disk = 'public',
    ): Media {
        $stored = $this->storeOptimized($file, $this->directoryFor($mediable), $disk);

        if ($isPrimary) {
            $mediable->media()->update(['is_primary' => false]);
        }

        return Media::create([
            'mediable_type' => $mediable::class,
            'mediable_id' => $mediable->getKey(),
            'disk' => $disk,
            'path' => $stored['path'],
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $stored['mime_type'],
            'size' => Storage::disk($disk)->size($stored['path']),
            'alt_text' => $altText,
            'is_primary' => $isPrimary,
        ]);
    }

    public function detach(Media $media): void
    {
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();
    }

    /**
     * Flips this media to primary and every sibling on the same mediable
     * (same type+id) to not-primary, mirroring the unset-then-set done at
     * attach() time — kept atomic so a request can never leave two rows
     * both marked primary.
     */
    public function makePrimary(Media $media): Media
    {
        Media::query()->getConnection()->transaction(function () use ($media) {
            Media::query()
                ->where('mediable_type', $media->mediable_type)
                ->where('mediable_id', $media->mediable_id)
                ->where('id', '!=', $media->id)
                ->update(['is_primary' => false]);

            $media->update(['is_primary' => true]);
        });

        return $media->fresh();
    }

    /**
     * Reorders every media row belonging to one mediable to match
     * $orderedIds' position — ids that don't belong to this mediable are
     * silently ignored rather than erroring, so a stale client-side list
     * (e.g. a photo deleted by someone else moments earlier) can't corrupt
     * other rows' ordering.
     *
     * @param  list<int>  $orderedIds
     */
    public function reorder(Model&IsMediable $mediable, array $orderedIds): void
    {
        $ownIds = $mediable->media()->pluck('id')->all();

        Media::query()->getConnection()->transaction(function () use ($orderedIds, $ownIds) {
            foreach (array_values(array_intersect($orderedIds, $ownIds)) as $position => $id) {
                Media::query()->where('id', $id)->update(['sort_order' => $position]);
            }
        });
    }

    public function updateFocus(Media $media, float $x, float $y): Media
    {
        $media->update(['focus_x' => $x, 'focus_y' => $y]);

        return $media->fresh();
    }

    /**
     * The pack-size-specific photo shown when this variant is selected
     * (see ProductPage.tsx's getGalleryImagesForVariant) — one photo per
     * variant, no gallery, same convention FunnelSeeder::seedVariantImage()
     * already seeds by hand. Replaces the existing photo in place if the
     * variant already has one, so its Media id (and any focus point set on
     * it) survives a re-upload; otherwise attaches a new primary photo.
     */
    public function attachOrReplaceVariantPhoto(ProductVariant $variant, UploadedFile $file, ?string $altText = null): Media
    {
        $existing = $variant->media()->first();

        return $existing !== null
            ? $this->replace($existing, $file, $altText)
            : $this->attach($variant, $file, altText: $altText, isPrimary: true);
    }

    private function directoryFor(Model&IsMediable $mediable): string
    {
        return Str::plural(Str::snake(class_basename($mediable)));
    }

    /**
     * Images get resized to a sane max dimension and re-encoded as WebP
     * before being stored. Uploads were previously stored at whatever
     * resolution/format the browser sent — product photos routinely
     * arrived as 1600x2000+ PNGs several MB each, shipped unchanged to
     * every visitor regardless of whether that photo was shown full-size
     * or as a 64px thumbnail, which is what made images so slow to load.
     * Plain GD, no new Composer dependency — matches what the shared host
     * already has (see [[images-performance]] memory, if saved). Anything
     * GD can't decode (video, PDF, an unsupported/corrupt image) is stored
     * untouched.
     *
     * @return array{path: string, mime_type: string}
     */
    private function storeOptimized(UploadedFile $file, string $directory, string $disk): array
    {
        $isImage = str_starts_with($file->getMimeType() ?? '', 'image/');
        $optimized = $isImage ? $this->resizeAndEncode((string) file_get_contents($file->getRealPath())) : null;

        if ($optimized === null) {
            return [
                'path' => $file->store($directory, $disk),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            ];
        }

        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.webp';
        Storage::disk($disk)->put($path, $optimized);

        return [
            'path' => $path,
            'mime_type' => 'image/webp',
        ];
    }

    /**
     * Re-runs an already-stored image through the same resize/WebP
     * pipeline storeOptimized() applies to new uploads — for backfilling
     * media uploaded before that existed (see the `media:optimize` artisan
     * command). No-op for anything that isn't an image, is already WebP,
     * or that GD can't decode.
     */
    public function reoptimize(Media $media): Media
    {
        if ($media->mime_type === null || ! str_starts_with($media->mime_type, 'image/') || $media->mime_type === 'image/webp') {
            return $media;
        }

        $optimized = $this->resizeAndEncode(Storage::disk($media->disk)->get($media->path));

        if ($optimized === null) {
            return $media;
        }

        $oldPath = $media->path;
        $newPath = trim(dirname($media->path), '/').'/'.Str::uuid()->toString().'.webp';

        Storage::disk($media->disk)->put($newPath, $optimized);
        Storage::disk($media->disk)->delete($oldPath);

        $media->update([
            'path' => $newPath,
            'mime_type' => 'image/webp',
            'size' => Storage::disk($media->disk)->size($newPath),
        ]);

        return $media->fresh();
    }

    /**
     * Images get resized to a sane max dimension and re-encoded as WebP.
     * Uploads were previously stored at whatever resolution/format the
     * browser sent — product photos routinely arrived as 1600x2000+ PNGs
     * several MB each, shipped unchanged to every visitor regardless of
     * whether that photo was shown full-size or as a 64px thumbnail, which
     * is what made images so slow to load. Plain GD, no new Composer
     * dependency — matches what the shared host already has. Returns null
     * (caller stores the original untouched) for anything GD can't decode
     * — video, PDF, or an unsupported/corrupt image.
     */
    private function resizeAndEncode(string $contents): ?string
    {
        $decoded = @imagecreatefromstring($contents);

        if ($decoded === false) {
            return null;
        }

        imagepalettetotruecolor($decoded);
        imagealphablending($decoded, true);
        imagesavealpha($decoded, true);

        $width = imagesx($decoded);
        $height = imagesy($decoded);
        $maxWidth = 1600;

        if ($width > $maxWidth) {
            $newHeight = (int) round($height * ($maxWidth / $width));
            $resized = imagecreatetruecolor($maxWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $decoded, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);
            imagedestroy($decoded);
            $decoded = $resized;
        }

        ob_start();
        imagewebp($decoded, quality: 82);
        $encoded = ob_get_clean();
        imagedestroy($decoded);

        return $encoded;
    }
}
