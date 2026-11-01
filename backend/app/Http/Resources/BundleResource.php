<?php

namespace App\Http\Resources;

use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Services\CartPricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A kit as the storefront sees it (GET /v1/bundles, and embedded in a kit
 * cart line - see Cart\CartItemResource): its single stored price, the
 * components' live separate-purchase total (retail_value) and the
 * resulting saving for "you save X" messaging, and the resolved
 * components themselves. Expects ProductBundle::COMPONENT_EAGER_LOAD.
 *
 * @mixin ProductBundle
 */
class BundleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CartPricingService $pricing */
        $pricing = app(CartPricingService::class);
        $amount = (float) $this->amount;
        $retailValue = $this->resource->retailValue($this->currency);
        $isPurchasable = $pricing->isBundlePurchasable($this->resource);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'badge' => $this->badge,
            'currency' => $this->currency,
            'amount' => $amount,
            'retail_value' => $retailValue,
            'savings' => $retailValue > $amount ? round($retailValue - $amount, 2) : 0.0,
            'status' => $this->status->value,
            'sort_order' => $this->sort_order,
            // The kit's catalog shell variant (see KitShellProductService) -
            // what its reviews are filed against.
            'shell_product_variant_id' => $this->shell_product_variant_id,
            // The shell product's own photos - admin-managed via
            // /admin/products like any other product's gallery (see
            // ProductMediaManager.tsx), shown on the funnel page's kit
            // selector (BundleRadioSelector/PurchasePanel). Empty until an
            // admin uploads some; the frontend falls back to the static
            // KIT_GALLERY_IMAGES set in that case.
            'gallery' => $this->shellProductVariant?->product?->relationLoaded('media')
                ? MediaResource::collection($this->shellProductVariant->product->media)
                : [],
            'is_available' => $isPurchasable && $pricing->maxBundleQuantityFor($this->resource) > 0,
            'items' => $this->items->map(fn (ProductBundleItem $component) => [
                'product_variant_id' => $component->product_variant_id,
                'quantity' => $component->quantity,
                'sku' => $component->productVariant?->sku,
                'variant_name' => $component->productVariant?->name,
                'pack_size' => $component->productVariant?->pack_size,
                'unit_price' => $component->productVariant?->priceFor($this->currency) !== null
                    ? (float) $component->productVariant->priceFor($this->currency)->amount
                    : null,
                'product' => $component->productVariant?->product !== null ? [
                    'id' => $component->productVariant->product->id,
                    'name' => $component->productVariant->product->name,
                    'slug' => $component->productVariant->product->slug,
                    'primary_image' => $component->productVariant->product->relationLoaded('primaryMedia') && $component->productVariant->product->primaryMedia
                        ? new MediaResource($component->productVariant->product->primaryMedia)
                        : null,
                ] : null,
            ])->values(),
        ];
    }
}
