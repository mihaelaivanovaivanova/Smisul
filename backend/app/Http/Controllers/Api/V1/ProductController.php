<?php

namespace App\Http\Controllers\Api\V1;

use App\DataTransferObjects\ProductFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductIndexRequest;
use App\Http\Requests\Review\ReviewIndexRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductVariantResource;
use App\Http\Resources\ReviewResource;
use App\Services\ProductService;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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

        return ReviewResource::collection($this->reviews->listForProduct($product, $sort, $page));
    }

    public function reviewsSummary(string $slug): JsonResponse
    {
        $product = $this->products->findBySlug($slug, publishedOnly: true);

        return response()->json(['data' => $this->reviews->summaryFor($product)]);
    }
}
