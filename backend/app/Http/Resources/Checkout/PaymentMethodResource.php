<?php

namespace App\Http\Resources\Checkout;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps an array{method: PaymentMethod, available: bool} — `available`
 * reflects the current shipping carrier (see
 * PaymentService::availablePaymentMethods()); checkout still lists every
 * offerable method (see PaymentService::offerableMethods()) even when
 * unavailable, so the frontend can render cash on delivery greyed out with
 * an explanation instead of hiding it outright when Speedy is selected.
 */
class PaymentMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'value' => $this->resource['method']->value,
            'label' => $this->resource['method']->label(),
            'available' => $this->resource['available'],
        ];
    }
}
