<?php

namespace App\Http\Resources\Checkout;

use App\DataTransferObjects\Shipping\SettlementData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SettlementData
 */
class SettlementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'municipality' => $this->municipality,
            'region' => $this->region,
            'postal_code' => $this->postalCode,
        ];
    }
}
