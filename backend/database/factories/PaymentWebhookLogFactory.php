<?php

namespace Database\Factories;

use App\Enums\PaymentProvider;
use App\Models\PaymentWebhookLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentWebhookLog>
 */
class PaymentWebhookLogFactory extends Factory
{
    protected $model = PaymentWebhookLog::class;

    public function definition(): array
    {
        return [
            'payment_id' => null,
            'provider' => PaymentProvider::ICard,
            'event_type' => 'payment.paid',
            'provider_reference' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'signature_valid' => true,
            'payload' => ['event' => 'payment.paid'],
            'processed_at' => now(),
        ];
    }
}
