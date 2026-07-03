<?php

namespace App\Http\Resources;

use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Promotion
 */
class PromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'value' => (float) $this->value,
            'code' => $this->code,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'usage_limit' => $this->usage_limit,
            'used_count' => $this->used_count,
            'is_active' => $this->is_active,
            'is_currently_valid' => $this->isCurrentlyValid(),
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
        ];
    }
}
