<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTransferObjects\ProductData;
use App\DataTransferObjects\ProductFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductIndexRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\AdminActionLogger;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $products,
        private readonly AdminActionLogger $actionLogger,
    ) {}

    public function index(ProductIndexRequest $request)
    {
        $filters = ProductFilterData::fromArray($request->validated());

        $products = $this->products->list($filters, publishedOnly: false);

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->products->create(ProductData::fromArray($request->validated()));

        $this->actionLogger->log($request->user(), 'product.created', $product);

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource(
            $product->load(['categories.promotions', 'variants.prices', 'variants.inventory', 'media', 'seo', 'promotions'])
        );
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product = $this->products->update($product, ProductData::fromArray($request->validated()));

        $this->actionLogger->log($request->user(), 'product.updated', $product);

        return new ProductResource($product);
    }

    public function destroy(Product $product): Response
    {
        $this->authorize('delete', $product);

        $this->actionLogger->log(request()->user(), 'product.deleted', $product);

        $this->products->delete($product);

        return response()->noContent();
    }
}
