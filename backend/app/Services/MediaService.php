<?php

namespace App\Services;

use App\DataTransferObjects\Admin\MediaFilterData;
use App\Models\Contracts\IsMediable;
use App\Models\Media;
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
        $newPath = $file->store(dirname($media->path), $media->disk);

        Storage::disk($media->disk)->delete($media->path);

        $media->update([
            'path' => $newPath,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'alt_text' => $altText ?? $media->alt_text,
        ]);

        return $media->fresh();
    }

    /**
     * Attach an uploaded file to any "mediable" model. Only stores the file
     * and a database reference — no resizing/thumbnailing pipeline, which
     * is out of scope until an actual upload UI exists.
     */
    public function attach(
        Model&IsMediable $mediable,
        UploadedFile $file,
        ?string $altText = null,
        bool $isPrimary = false,
        string $disk = 'public',
    ): Media {
        $path = $file->store($this->directoryFor($mediable), $disk);

        if ($isPrimary) {
            $mediable->media()->update(['is_primary' => false]);
        }

        return Media::create([
            'mediable_type' => $mediable::class,
            'mediable_id' => $mediable->getKey(),
            'disk' => $disk,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
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

    private function directoryFor(Model&IsMediable $mediable): string
    {
        return Str::plural(Str::snake(class_basename($mediable)));
    }
}
