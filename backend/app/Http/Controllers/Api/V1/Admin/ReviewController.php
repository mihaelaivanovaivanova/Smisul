<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTransferObjects\Admin\ReviewFilterData;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkModerateReviewRequest;
use App\Http\Requests\Admin\RejectReviewRequest;
use App\Http\Requests\Admin\ReplyReviewRequest;
use App\Http\Requests\Admin\ReviewIndexRequest;
use App\Http\Resources\Admin\ReviewResource;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    public function index(ReviewIndexRequest $request): AnonymousResourceCollection
    {
        return ReviewResource::collection($this->reviews->listForAdmin(ReviewFilterData::fromArray($request->validated())));
    }

    public function approve(Review $review): ReviewResource
    {
        return new ReviewResource($this->reviews->approve($review));
    }

    public function reject(RejectReviewRequest $request, Review $review): ReviewResource
    {
        return new ReviewResource($this->reviews->reject($review, $request->validated('reason')));
    }

    public function hide(Review $review): ReviewResource
    {
        return new ReviewResource($this->reviews->hide($review));
    }

    /**
     * A permanent removal — unlike hide/reject, this isn't reversible via
     * the approve action. Distinct from the customer's own delete (same
     * underlying ReviewService::delete, no ownership check needed here
     * since the 'admin' middleware already gates the whole route group).
     */
    public function destroy(Review $review): Response
    {
        $this->reviews->delete($review);

        return response()->noContent();
    }

    public function reply(ReplyReviewRequest $request, Review $review): ReviewResource
    {
        return new ReviewResource($this->reviews->reply($review, $request->user(), $request->validated('reply')));
    }

    public function bulkModerate(BulkModerateReviewRequest $request): JsonResponse
    {
        $updated = $this->reviews->bulkModerate(
            $request->validated('review_ids'),
            ReviewStatus::from($request->validated('status')),
        );

        return response()->json(['data' => ['updated' => $updated]]);
    }

    public function statistics(): JsonResponse
    {
        return response()->json(['data' => $this->reviews->statistics()]);
    }
}
