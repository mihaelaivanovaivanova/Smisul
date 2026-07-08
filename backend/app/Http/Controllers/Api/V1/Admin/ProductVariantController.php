<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTransferObjects\ProductVariantData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\InventoryUpdateRequest;
use App\Http\Requests\Product\StoreProductVariantRequest;
use App\Http\Requests\Product\UpdateProductVariantRequest;
use App\Http\Resources\InventoryResource;
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

    /**
     * Sets a variant's on-hand stock. Kept separate from update() (which
     * only ever touches sku/name/pack_size/etc — see ProductVariantData)
     * the same way price has its own endpoint: stock and price are each
     * their own concern, not bundled into the general variant attributes.
     */
    public function updateInventory(InventoryUpdateRequest $request, Product $product, ProductVariant $variant): JsonResponse
    {
        $variant->inventory()->update($request->validated());

        return (new InventoryResource($variant->inventory()->firstOrFail()))->response()->setStatusCode(200);
    }
}
