<?php

namespace App\Http\Controllers\Api\V1;

use App\DataTransferObjects\ProductFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductIndexRequest;
use App\Http\Requests\Review\ReviewIndexRequest;
use App\Http\Requests\Review\SubmitReviewRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductVariantResource;
use App\Http\Resources\ReviewResource;
use App\Models\Order;
use App\Services\ProductService;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\URL;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $products,
        private readonly ReviewService $reviews,
    ) {}

    public function index(ProductIndexRequest $request)
    {
        $filters = ProductFilterData::fromArray($request->validated());

        $products = $this->products->list($filters, publishedOnly: true);

        return ProductResource::collection($products);
    }

    public function show(string $slug): ProductResource
    {
        // findBySlug() already eager-loads everything ProductResource needs
        // (see EloquentProductRepository) — no further ->load() required.
        $product = $this->products->findBySlug($slug, publishedOnly: true);

        return new ProductResource($product);
    }

    public function variants(string $slug)
    {
        $product = $this->products->findBySlug($slug, publishedOnly: true);

        return ProductVariantResource::collection(
            $product->variants()->with(['prices', 'inventory'])->get()
        );
    }

    public function reviews(ReviewIndexRequest $request, string $slug): AnonymousResourceCollection
    {
        $product = $this->products->findBySlug($slug, publishedOnly: true);
        $sort = $request->validated('sort') ?? 'newest';
        $page = (int) ($request->validated('page') ?? 1);
        $perPage = (int) ($request->validated('per_page') ?? 4);

        return ReviewResource::collection($this->reviews->listForProduct($product, $sort, $page, $perPage));
    }

    public function reviewsSummary(string $slug): JsonResponse
    {
        $product = $this->products->findBySlug($slug, publishedOnly: true);

        return response()->json(['data' => $this->reviews->summaryFor($product)]);
    }

    /**
     * Powers the storefront's "Add a review" wizard — public, no auth
     * required (eligibility is checked by the typed email, not a login
     * session, so guest checkouts can be reviewed too). Always returns the
     * same generic message regardless of whether the email actually
     * matched an eligible order — see ReviewService::submitForConfirmation.
     */
    public function submitReview(SubmitReviewRequest $request, string $slug): JsonResponse
    {
        $product = $this->products->findBySlug($slug, publishedOnly: true);

        $this->reviews->submitForConfirmation(
            $product,
            $request->validated(),
            preVerified: $this->hasValidReviewIdentityLink($request),
        );

        return response()->json([
            'message' => 'Ако имейлът отговаря на доставена поръчка за този продукт, ще получиш писмо за потвърждение.',
        ]);
    }

    /**
     * True only if order_id/expires/signature were all submitted AND they
     * really are a still-valid signature for THAT order's own
     * orders.review-identity link (see OrderThirtyDayReminderMail::reviewUrl)
     * AND the submitted email matches that order's customer_email - never
     * trusted as a bare client-asserted flag, since a guest wizard
     * submission could otherwise just claim "preVerified" on any email to
     * skip the confirmation step entirely. A forged/expired/mismatched
     * signature here is a silent "not pre-verified" (falls back to the
     * normal confirm-by-email flow), not a rejected request - the fields
     * are optional precisely because every other submitReview caller
     * (the plain guest wizard) never sends them.
     */
    private function hasValidReviewIdentityLink(SubmitReviewRequest $request): bool
    {
        $orderId = $request->validated('order_id');
        $expires = $request->validated('expires');
        $signature = $request->validated('signature');

        if ($orderId === null || $expires === null || $signature === null) {
            return false;
        }

        $baseUrl = URL::route('orders.review-identity', ['order' => $orderId]);
        $signedRequest = Request::create("{$baseUrl}?expires={$expires}&signature={$signature}");

        if (! URL::hasValidSignature($signedRequest)) {
            return false;
        }

        $order = Order::find($orderId);

        return $order !== null && $order->customer_email === $request->validated('email');
    }
}
