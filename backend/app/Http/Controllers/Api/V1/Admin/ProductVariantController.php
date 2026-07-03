<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTransferObjects\ProductVariantData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductVariantRequest;
use App\Http\Requests\Product\UpdateProductVariantRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductVariantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductVariantController extends Controller
{
    public function __construct(private readonly ProductVariantService $variants) {}

    public function store(StoreProductVariantRequest $request, Product $product): JsonResponse
    {
        $variant = $this->variants->create($product, ProductVariantData::fromArray($request->validated()));

        return (new ProductVariantResource($variant->load('inventory')))->response()->setStatusCode(201);
    }

    public function update(UpdateProductVariantRequest $request, Product $product, ProductVariant $variant): ProductVariantResource
    {
        $variant = $this->variants->update($variant, ProductVariantData::fromArray($request->validated()));

        return new ProductVariantResource($variant->load(['prices', 'inventory']));
    }

    public function destroy(Product $product, ProductVariant $variant): Response
    {
        $this->authorize('update', $product);

        $this->variants->delete($variant);

        return response()->noContent();
    }
}
