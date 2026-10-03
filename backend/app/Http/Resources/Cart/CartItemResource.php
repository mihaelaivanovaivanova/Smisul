<?php

namespace App\Http\Resources\Cart;

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
        $price = $variant ? $pricing->unitPrice($variant, $this->currency) : null;

        $compareAtUnitPrice = $price?->compare_at_amount !== null ? (float) $price->compare_at_amount : null;
        $isOnSale = $price?->isOnSale() ?? false;

        // No real sale on this exact price row — fall back to the bundle
        // "was" price (pack price vs. buying singly) so a multi-piece
        // pack's cart line shows the same savings the product/funnel page
        // already advertised when it was added.
        if (! $isOnSale && $variant !== null && $price !== null) {
            $bundleCompareAt = $pricing->bundleCompareAtUnitPrice($variant, $this->currency);

            if ($bundleCompareAt !== null && $bundleCompareAt > $price->amount) {
                $compareAtUnitPrice = $bundleCompareAt;
                $isOnSale = true;
            }
        }

        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'product_variant' => new ProductVariantResource($variant),
            'unit_price' => $price ? (float) $price->amount : null,
            'compare_at_unit_price' => $compareAtUnitPrice,
            'is_on_sale' => $isOnSale,
            'line_total' => $pricing->lineTotal($this->resource, $this->currency),
            'is_available' => $pricing->isItemAvailable($this->resource),
            'max_quantity' => $variant ? $pricing->maxQuantityFor($variant, $this->quantity) : 0,
        ];
    }
}
