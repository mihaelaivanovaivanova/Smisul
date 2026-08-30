<?php

namespace App\Http\Resources;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'pack_size' => $this->pack_size,
            'is_default' => $this->is_default,
            'status' => $this->status->value,
            'prices' => PriceResource::collection($this->whenLoaded('prices')),
            'inventory' => new InventoryResource($this->whenLoaded('inventory')),
            // Empty for most variants today (only a pack size with its own
            // real product photo gets one) — the frontend gallery falls
            // back to the product's own media when this is empty, see
            // getGalleryImagesForVariant() in productCatalog.ts.
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
                'primary_image' => $this->product->relationLoaded('primaryMedia') && $this->product->primaryMedia
                    ? new MediaResource($this->product->primaryMedia)
                    : null,
            ]),
        ];
    }
}
