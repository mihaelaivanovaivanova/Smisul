<?php

namespace App\Http\Resources\Admin;

use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Complaint
 */
class ComplaintResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'complaint_number' => $this->complaint_number,
            'order' => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'customer_name' => $this->order->customerFullName(),
                'customer_email' => $this->order->customer_email,
            ],
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'resolution' => $this->resolution,
            'submitted_at' => $this->submitted_at,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
        ];
    }
}
