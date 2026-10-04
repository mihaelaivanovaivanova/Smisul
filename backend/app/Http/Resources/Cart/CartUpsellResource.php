<?php

namespace App\Http\Resources\Cart;

use App\Http\Resources\MediaResource;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the array CartService::upsellOffer() returns (never an Eloquent
 * model directly) — deliberately narrow: only amount/compare_at_amount for
 * display, never the variant's full price row, so upsell_amount itself
 * (admin-only elsewhere, see Admin\PriceResource) is never exposed as a
 * field name a client could key off of generically.
 *
 * @mixin array{product: Product, variant: ProductVariant, price: Price}
 */
class CartUpsellResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->resource['product'];
        $variant = $this->resource['variant'];
        $price = $this->resource['price'];

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'primary_image' => $product->primaryMedia ? new MediaResource($product->primaryMedia) : null,
            ],
            'variant_id' => $variant->id,
            'amount' => (float) $price->upsell_amount,
            'compare_at_amount' => (float) $price->amount,
            'currency' => $price->currency,
        ];
    }
}
