<?php

namespace App\Http\Resources\Checkout;

use App\DataTransferObjects\Shipping\ShippingOfficeData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShippingOfficeData
 */
class ShippingOfficeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'carrier' => $this->carrier->value,
            'type' => $this->type->value,
            'name' => $this->name,
            'city' => $this->city,
            'address' => $this->address,
        ];
    }
}
