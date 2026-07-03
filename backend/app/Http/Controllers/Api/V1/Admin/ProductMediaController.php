<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\Product;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductMediaController extends Controller
{
    public function __construct(private readonly MediaService $mediaService) {}

    public function store(StoreProductMediaRequest $request, Product $product): JsonResponse
    {
        $media = $this->mediaService->attach(
            $product,
            $request->file('file'),
            altText: $request->validated('alt_text'),
            isPrimary: $request->boolean('is_primary'),
        );

        return (new MediaResource($media))->response()->setStatusCode(201);
    }

    public function destroy(Product $product, Media $media): Response
    {
        $this->authorize('update', $product);

        $this->mediaService->detach($media);

        return response()->noContent();
    }
}
