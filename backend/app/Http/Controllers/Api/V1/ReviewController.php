<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Customer-facing "my reviews" CRUD, plus the "mark helpful" vote (open to
 * any authenticated user, not customer-only — see ReviewPolicy). Public,
 * product-scoped review browsing lives on ProductController instead.
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Review::class);

        return ReviewResource::collection($this->reviews->listForUser($request->user()));
    }

    public function store(StoreReviewRequest $request): JsonResponse
    {
        $variant = ProductVariant::findOrFail($request->validated('product_variant_id'));
        $order = Order::findOrFail($request->validated('order_id'));

        $review = $this->reviews->create($request->user(), $variant, $order, $request->validated());

        return (new ReviewResource($review))->response()->setStatusCode(201);
    }

    public function update(UpdateReviewRequest $request, Review $review): ReviewResource
    {
        return new ReviewResource($this->reviews->update($review, $request->validated()));
    }

    public function destroy(Request $request, Review $review): Response
    {
        $this->authorize('delete', $review);

        $this->reviews->delete($review);

        return response()->noContent();
    }

    public function markHelpful(Request $request, Review $review): JsonResponse
    {
        $this->authorize('markHelpful', $review);

        return response()->json(['data' => $this->reviews->markHelpful($review, $request->user())]);
    }
}
