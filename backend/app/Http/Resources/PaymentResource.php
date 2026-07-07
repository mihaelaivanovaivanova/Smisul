<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'provider' => $this->provider->value,
            'payment_method' => $this->payment_method->value,
            'status' => $this->status->value,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'modal_session' => isset($this->raw_response['modal_token']) ? [
                'token' => $this->raw_response['modal_token'],
                'modal_js_url' => $this->raw_response['modal_js_url'],
                'theme' => $this->raw_response['theme'] ?? 'dark',
            ] : null,
            'wallet_session' => $this->raw_response['wallet'] ?? null,
            'initiated_at' => $this->initiated_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
