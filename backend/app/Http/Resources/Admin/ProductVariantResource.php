<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\ProductVariantResource as BaseProductVariantResource;
use Illuminate\Http\Request;

/**
 * Swaps in the admin PriceResource (adds upsell_amount) for the 'prices'
 * key - everything else is identical to the public variant shape.
 */
class ProductVariantResource extends BaseProductVariantResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'prices' => PriceResource::collection($this->whenLoaded('prices')),
        ];
    }
}
