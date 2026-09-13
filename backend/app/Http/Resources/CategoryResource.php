<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'children' => self::collection($this->whenLoaded('children')),
            // Unlike every seeded product, no category has a seo row yet
            // (see CategoryService::syncSeo()) - the naive whenLoaded('seo')
            // pattern ProductResource uses would try to read properties off
            // a loaded-but-null relation here, logging a PHP warning on
            // every single category fetch. when() only evaluates the
            // closure once the relation is actually loaded, and the
            // closure itself checks for null before wrapping it.
            'seo' => $this->when($this->relationLoaded('seo'), fn () => $this->seo ? new SeoResource($this->seo) : null),
        ];
    }
}
