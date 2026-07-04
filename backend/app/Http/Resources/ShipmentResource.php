<?php

namespace App\Http\Resources;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Shipment
 */
class ShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'carrier' => $this->carrier->value,
            'delivery_type' => $this->delivery_type->value,
            'office_id' => $this->office_id,
            'office_name' => $this->office_name,
            'tracking_number' => $this->tracking_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'price' => (float) $this->price,
            'currency' => $this->currency,
            'estimated_delivery_at' => $this->estimated_delivery_at?->toIso8601String(),
            'events' => ShipmentStatusEventResource::collection($this->whenLoaded('statusEvents')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
