<?php

namespace App\Services;

use App\Models\Contracts\IsMediable;
use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
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

    private function directoryFor(Model&IsMediable $mediable): string
    {
        return Str::plural(Str::snake(class_basename($mediable)));
    }
}
