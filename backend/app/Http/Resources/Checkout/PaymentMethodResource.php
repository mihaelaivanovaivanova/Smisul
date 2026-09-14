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
 * an explanation instead of hiding it outright when a non-Speedy carrier
 * is selected.
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
            // See PaymentMethod::fee() - zero for every method except
            // cash on delivery. The frontend needs this before an order
            // even exists (to show "+X" next to the option and total it
            // into the running checkout total), so it's read from here
            // rather than only appearing on the order after placement.
            'fee' => $this->resource['method']->fee(),
        ];
    }
}
