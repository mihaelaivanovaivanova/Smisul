<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTransferObjects\Admin\MediaFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MediaIndexRequest;
use App\Http\Requests\Admin\MediaReplaceRequest;
use App\Http\Resources\Admin\MediaResource;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MediaController extends Controller
{
    public function __construct(private readonly MediaService $media) {}

    public function index(MediaIndexRequest $request): AnonymousResourceCollection
    {
        $media = $this->media->list(MediaFilterData::fromArray($request->validated()));

        return MediaResource::collection($media);
    }

    public function replace(MediaReplaceRequest $request, Media $media): MediaResource
    {
        $this->authorize('update', $media);

        $updated = $this->media->replace($media, $request->file('file'), $request->validated('alt_text'));

        return new MediaResource($updated);
    }

    public function destroy(Media $media): Response
    {
        $this->authorize('delete', $media);

        $this->media->detach($media);

        return response()->noContent();
    }
}
