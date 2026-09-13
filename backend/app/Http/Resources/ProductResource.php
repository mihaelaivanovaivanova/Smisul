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
            // A product with no seo row yet (e.g. one just created via the
            // admin panel, before any SEO copy is filled in) previously
            // made this log a PHP warning on every single fetch - the
            // naive new SeoResource($this->whenLoaded('seo')) tries to
            // read properties off a loaded-but-null relation. when() only
            // evaluates the closure once the relation is actually loaded,
            // and the closure itself checks for null before wrapping it.
            'seo' => $this->when($this->relationLoaded('seo'), fn () => $this->seo ? new SeoResource($this->seo) : null),
            'active_promotions' => $this->whenLoaded(
                'promotions',
                fn () => PromotionResource::collection($this->activePromotions()),
            ),
        ];
    }
}
