<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\OrderResource as BaseOrderResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\ShipmentResource;
use Illuminate\Http\Request;

/**
 * Adds admin-only fields (every payment attempt, the shipment) to the
 * customer-facing OrderResource rather than duplicating its field list —
 * see the sprint's "reuse existing services/resources" instruction.
 */
class OrderResource extends BaseOrderResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'user_id' => $this->user_id,
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'shipment' => $this->whenLoaded('shipment', fn () => $this->shipment !== null ? new ShipmentResource($this->shipment) : null),
        ];
    }
}
