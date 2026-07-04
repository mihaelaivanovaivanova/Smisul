<?php

namespace App\Http\Resources\Checkout;

use App\DataTransferObjects\Shipping\ShippingQuoteData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShippingQuoteData
 */
class ShippingQuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'carrier' => $this->carrier->value,
            'delivery_type' => $this->deliveryType->value,
            'price' => $this->price,
            'currency' => $this->currency,
            'estimated_delivery' => $this->estimatedDelivery,
        ];
    }
}
