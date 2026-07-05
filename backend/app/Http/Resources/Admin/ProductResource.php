<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\ProductResource as BaseProductResource;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

/**
 * Adds a simplified "quantity"/"price" pair — read off the product's
 * default variant (or its first variant, if none is flagged default) —
 * so the admin product form can treat single-variant products as having
 * one quantity and one price, without exposing the full variant model.
 * Null when the product has no variant at all yet.
 */
class ProductResource extends BaseProductResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductVariant|null $variant */
        $variant = $this->variants->firstWhere('is_default', true) ?? $this->variants->first();
        $price = $variant?->prices->firstWhere('currency', 'EUR')?->amount;

        return [
            ...parent::toArray($request),
            'quantity' => $variant?->inventory?->quantity_on_hand,
            'price' => $price !== null ? (float) $price : null,
            // Falls back to a live count when the caller didn't eager-load
            // it via withCount('favorites') — an extra query per row on an
            // uncounted list, acceptable at this catalog's scale.
            'favorites_count' => $this->favorites_count ?? $this->favorites()->count(),
        ];
    }
}
