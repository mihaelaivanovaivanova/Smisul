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
        $isAdministrator = $request->user()?->isAdministrator() === true;

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'provider' => $this->provider->value,
            'payment_method' => $this->payment_method->value,
            'status' => $this->status->value,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'gateway_environment' => $this->gateway_environment,
            'gateway_transaction_reference' => $this->when($isAdministrator, $this->gateway_transaction_reference),
            'operation_status' => $this->when($isAdministrator, $this->operation_status),
            'operation_code' => $this->when($isAdministrator, $this->operation_code),
            'operation_message' => $this->when($isAdministrator, $this->operation_message),
            'approval_code' => $this->when($isAdministrator, $this->approval_code),
            'masked_pan' => $this->when($isAdministrator, $this->masked_pan),
            'card_type' => $this->when($isAdministrator, $this->card_type),
            'cardholder_name' => $this->when($isAdministrator, $this->cardholder_name),
            'refunded_amount' => (float) $this->refunded_amount,
            'modal_session' => isset($this->raw_response['modal_token']) ? [
                'token' => $this->raw_response['modal_token'],
                'modal_js_url' => $this->raw_response['modal_js_url'],
                'theme' => $this->raw_response['theme'] ?? 'dark',
            ] : null,
            'wallet_session' => $this->raw_response['wallet'] ?? null,
            'initiated_at' => $this->initiated_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'reversed_at' => $this->reversed_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'callback_logs' => $this->when($isAdministrator, fn () => $this->webhookLogs->map(fn ($log) => [
                'id' => $log->id,
                'event_type' => $log->event_type,
                'provider_reference' => $log->provider_reference,
                'signature_valid' => (bool) $log->signature_valid,
                'error_message' => $log->error_message,
                'payload' => $log->payload,
                'processed_at' => $log->processed_at?->toIso8601String(),
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values()),
            'transactions' => $this->when($isAdministrator, fn () => $this->transactions->map(fn ($transaction) => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'status' => $transaction->status->value,
                'payload' => $transaction->raw_payload,
                'created_at' => $transaction->created_at?->toIso8601String(),
            ])->values()),
        ];
    }
}
