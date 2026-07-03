<?php

namespace App\Http\Resources;

use App\Models\Price;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Price
 */
class PriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'currency' => $this->currency,
            'amount' => (float) $this->amount,
            'compare_at_amount' => $this->compare_at_amount !== null ? (float) $this->compare_at_amount : null,
            'is_on_sale' => $this->isOnSale(),
        ];
    }
}
