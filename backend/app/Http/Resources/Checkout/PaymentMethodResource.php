<?php

namespace App\Http\Resources\Checkout;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps an array{method: PaymentMethod, available: bool} — every method
 * PaymentService::availablePaymentMethods() returns is enabled, so
 * `available` is always true today. Kept as a field (not collapsed to a
 * plain value list) so a future disabled-but-listed method doesn't need a
 * response-shape change.
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
