<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ReorderMediaRequest;
use App\Http\Requests\Product\StoreProductMediaRequest;
use App\Http\Requests\Product\UpdateMediaFocusRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\Product;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        $this->assertBelongsToProduct($product, $media);

        $this->mediaService->detach($media);

        return response()->noContent();
    }

    public function makePrimary(Product $product, Media $media): MediaResource
    {
        $this->authorize('update', $product);
        $this->assertBelongsToProduct($product, $media);

        return new MediaResource($this->mediaService->makePrimary($media));
    }

    public function reorder(ReorderMediaRequest $request, Product $product): JsonResponse
    {
        $this->mediaService->reorder($product, $request->validated('media_ids'));

        return response()->json(['data' => MediaResource::collection($product->media()->orderBy('sort_order')->get())]);
    }

    public function updateFocus(UpdateMediaFocusRequest $request, Product $product, Media $media): MediaResource
    {
        $this->assertBelongsToProduct($product, $media);

        return new MediaResource($this->mediaService->updateFocus($media, $request->validated('focus_x'), $request->validated('focus_y')));
    }

    private function assertBelongsToProduct(Product $product, Media $media): void
    {
        if ($media->mediable_type !== Product::class || $media->mediable_id !== $product->id) {
            throw new NotFoundHttpException;
        }
    }
}
