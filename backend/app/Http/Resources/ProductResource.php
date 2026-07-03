<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'seo' => new SeoResource($this->whenLoaded('seo')),
            'active_promotions' => $this->whenLoaded(
                'promotions',
                fn () => PromotionResource::collection($this->activePromotions()),
            ),
        ];
    }
}
