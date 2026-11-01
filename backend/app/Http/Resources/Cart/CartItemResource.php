<?php

namespace App\Http\Resources\Cart;

use App\Http\Resources\BundleResource;
use App\Http\Resources\ProductVariantResource;
use App\Models\CartItem;
use App\Services\CartPricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    /**
     * Takes the cart's currency explicitly (rather than lazy-loading
     * $item->cart->currency per item) since every item in a cart shares
     * the same currency and the parent Cart is already in hand — see
     * CartResource, which builds these directly instead of via
     * CartItemResource::collection().
     */
    public function __construct(CartItem $resource, private readonly string $currency)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CartPricingService $pricing */
        $pricing = app(CartPricingService::class);
        $variant = $this->productVariant;
        $bundle = $this->bundle_id !== null ? $this->bundle : null;
        $price = $pricing->effectivePrice($this->resource, $this->currency);

        $maxQuantity = match (true) {
            $bundle !== null => $pricing->maxBundleQuantityFor($bundle, $this->quantity),
            $variant !== null => $pricing->maxQuantityFor($variant, $this->quantity),
            default => 0,
        };

        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            // Exactly one of these two is non-null: an ordinary product
            // line has product_variant, a kit line has bundle (see CartItem).
            'product_variant' => $variant !== null ? new ProductVariantResource($variant) : null,
            'bundle' => $bundle !== null ? new BundleResource($bundle) : null,
            'unit_price' => $price ? (float) $price->amount : null,
            'compare_at_unit_price' => $price?->compare_at_amount !== null ? (float) $price->compare_at_amount : null,
            'is_on_sale' => $price?->isOnSale() ?? false,
            'line_total' => $pricing->lineTotal($this->resource, $this->currency),
            'is_available' => $pricing->isItemAvailable($this->resource),
            'max_quantity' => $maxQuantity,
        ];
    }
}
