<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTransferObjects\ProductVariantData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\InventoryUpdateRequest;
use App\Http\Requests\Product\StoreProductVariantRequest;
use App\Http\Requests\Product\UpdateProductVariantRequest;
use App\Http\Resources\Admin\CartReservationResource;
use App\Http\Resources\Admin\InventoryResource;
use App\Http\Resources\Admin\ProductVariantResource;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AdminActionLogger;
use App\Services\CartService;
use App\Services\InventoryService;
use App\Services\ProductVariantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductVariantController extends Controller
{
    public function __construct(
        private readonly ProductVariantService $variants,
        private readonly InventoryService $inventory,
        private readonly CartService $cart,
        private readonly AdminActionLogger $actionLogger,
    ) {}

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

    /**
     * Every cart currently holding reserved stock for this variant — lets
     * an admin looking at "on hand but 0 available" (quantity_on_hand vs.
     * quantity_reserved, see Admin\InventoryResource) see exactly what's
     * eating the difference, instead of guessing. See
     * InventoryService::reservationsFor()'s own docblock for the ordering.
     */
    public function reservations(Product $product, ProductVariant $variant): JsonResponse
    {
        return response()->json(['data' => CartReservationResource::collection($this->inventory->reservationsFor($variant))]);
    }

    /**
     * Clears one stale reservation by removing that line from whichever
     * cart holds it — reuses CartService::removeItem() (the exact same
     * removal a customer's own "remove from cart" triggers) rather than
     * adjusting quantity_reserved directly, so the invariant CartService's
     * own docblock states (quantity_reserved always equals the sum of that
     * variant's quantity across every cart_item) never has a path to drift.
     * An admin judgment call, not an automatic cleanup — see
     * CartReservationResource's own docblock on why no expiry exists yet.
     */
    public function releaseReservation(Request $request, Product $product, ProductVariant $variant, CartItem $cartItem): JsonResponse
    {
        abort_if($cartItem->product_variant_id !== $variant->id, 404);

        $holdingCart = $cartItem->cart;
        $this->cart->removeItem($holdingCart, $cartItem->id);

        $this->actionLogger->log($request->user(), 'inventory.reservation_released', $variant, [
            'cart_item_id' => $cartItem->id,
            'cart_id' => $holdingCart->id,
            'quantity' => $cartItem->quantity,
        ]);

        return response()->json(['data' => new InventoryResource($variant->inventory()->firstOrFail()->refresh())]);
    }
}
