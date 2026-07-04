<?php

namespace App\Http\Resources;

use App\Models\ShipmentStatusEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShipmentStatusEvent
 */
class ShipmentStatusEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status->value,
            'label' => $this->status->label(),
            'description' => $this->description,
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
