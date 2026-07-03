<?php

namespace App\Http\Resources;

use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderStatusHistory
 */
class OrderStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'previous_status' => $this->previous_status?->value,
            'changed_by' => $this->changedBy?->fullName(),
            'note' => $this->note,
            'changed_at' => $this->created_at->toIso8601String(),
        ];
    }
}
