<?php

namespace App\Http\Resources\Checkout;

use App\DataTransferObjects\Shipping\ShippingMethodData;
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
            'delivery_type' => $this->deliveryType->value,
            'label' => $this->label,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
            'estimated_delivery' => $this->estimatedDelivery,
            'requires_office' => $this->requiresOffice,
        ];
    }
}
