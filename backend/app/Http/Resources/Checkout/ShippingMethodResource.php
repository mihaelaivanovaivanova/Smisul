<?php

namespace App\Http\Resources\Checkout;

use App\DataTransferObjects\Checkout\ShippingMethodData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShippingMethodData
 */
class ShippingMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'carrier' => $this->carrier->value,
            'label' => $this->label,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
        ];
    }
}
