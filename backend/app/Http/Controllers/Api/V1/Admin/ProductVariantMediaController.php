<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreVariantMediaRequest;
use App\Http\Requests\Product\UpdateMediaFocusRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The pack-size-specific photo shown when a given variant is selected (see
 * ProductPage.tsx's getGalleryMediaForVariant) — one photo per variant, no
 * gallery, mirroring ProductMediaController's product-level split.
 */
class ProductVariantMediaController extends Controller
{
    public function __construct(private readonly MediaService $mediaService) {}

    public function store(StoreVariantMediaRequest $request, Product $product, ProductVariant $variant): JsonResponse
    {
        $media = $this->mediaService->attachOrReplaceVariantPhoto(
            $variant,
            $request->file('file'),
            altText: $request->validated('alt_text'),
        );

        return (new MediaResource($media))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Product $product, ProductVariant $variant): Response
    {
        $this->authorize('update', $product);

        $variant->media()->get()->each(fn ($media) => $this->mediaService->detach($media));

        return response()->noContent();
    }

    public function updateFocus(UpdateMediaFocusRequest $request, Product $product, ProductVariant $variant, Media $media): MediaResource
    {
        if ($media->mediable_type !== ProductVariant::class || $media->mediable_id !== $variant->id) {
            throw new NotFoundHttpException;
        }

        return new MediaResource($this->mediaService->updateFocus($media, $request->validated('focus_x'), $request->validated('focus_y')));
    }
}
