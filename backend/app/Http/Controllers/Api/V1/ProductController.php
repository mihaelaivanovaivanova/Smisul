<?php

namespace App\Http\Controllers\Api\V1;

use App\DataTransferObjects\ProductFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductIndexRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductVariantResource;
use App\Services\ProductService;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(ProductIndexRequest $request)
    {
        $filters = ProductFilterData::fromArray($request->validated());

        $products = $this->products->list($filters, publishedOnly: true);

        return ProductResource::collection($products);
    }

    public function show(string $slug): ProductResource
    {
        $product = $this->products->findBySlug($slug, publishedOnly: true);

        return new ProductResource($product->load(['categories', 'variants.prices', 'variants.inventory', 'media', 'seo']));
    }

    public function variants(string $slug)
    {
        $product = $this->products->findBySlug($slug, publishedOnly: true);

        return ProductVariantResource::collection(
            $product->variants()->with(['prices', 'inventory'])->get()
        );
    }
}
