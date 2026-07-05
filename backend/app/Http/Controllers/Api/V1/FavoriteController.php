<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Favorite\StoreFavoriteRequest;
use App\Http\Resources\FavoriteResource;
use App\Models\Favorite;
use App\Models\ProductVariant;
use App\Services\FavoriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class FavoriteController extends Controller
{
    public function __construct(private readonly FavoriteService $favorites) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Favorite::class);

        return FavoriteResource::collection($this->favorites->listForUser($request->user()));
    }

    public function store(StoreFavoriteRequest $request): JsonResponse
    {
        $variant = ProductVariant::findOrFail($request->validated('product_variant_id'));

        $favorite = $this->favorites->add($request->user(), $variant);

        return (new FavoriteResource($favorite))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Favorite $favorite): Response
    {
        $this->authorize('delete', $favorite);

        $this->favorites->remove($favorite);

        return response()->noContent();
    }

    /**
     * Lets a product/variant page check its own favorited state without
     * fetching the customer's entire list — see FavoriteService for the
     * frontend's actual approach (loading the full list once), which this
     * endpoint exists as a lighter-weight alternative to.
     */
    public function check(Request $request, ProductVariant $productVariant): JsonResponse
    {
        $this->authorize('viewAny', Favorite::class);

        $favorite = $this->favorites->findFavorite($request->user(), $productVariant);

        return response()->json([
            'data' => [
                'is_favorited' => $favorite !== null,
                'favorite_id' => $favorite?->id,
            ],
        ]);
    }

    public function count(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Favorite::class);

        return response()->json(['data' => ['count' => $this->favorites->countForUser($request->user())]]);
    }
}
