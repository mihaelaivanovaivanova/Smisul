<?php

namespace App\Http\Resources;

use App\Models\Favorite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Favorite
 */
class FavoriteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at->toIso8601String(),
            'product_variant' => new ProductVariantResource($this->whenLoaded('productVariant')),
        ];
    }
}
