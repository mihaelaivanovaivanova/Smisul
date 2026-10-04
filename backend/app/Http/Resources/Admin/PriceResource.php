<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\PriceResource as BasePriceResource;
use Illuminate\Http\Request;

/**
 * Adds upsell_amount - an admin-editable, cross-sell-only price that must
 * never reach the public storefront API (see CartPricingService::
 * effectivePrice() for where it's actually applied; the public
 * PriceResource this extends is also what the customer-facing cart/product
 * endpoints use, so that field is deliberately absent there).
 */
class PriceResource extends BasePriceResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'upsell_amount' => $this->upsell_amount !== null ? (float) $this->upsell_amount : null,
        ];
    }
}
