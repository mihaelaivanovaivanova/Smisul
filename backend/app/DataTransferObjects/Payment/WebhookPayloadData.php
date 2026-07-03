<?php

namespace App\DataTransferObjects\Payment;

use App\Enums\PaymentStatus;

/**
 * A gateway-agnostic view of a webhook delivery, normalized by the
 * gateway's own parseWebhook() implementation from its provider-specific
 * payload shape. PaymentService only ever deals with this shape, never the
 * raw request — so adding a second provider doesn't touch webhook handling.
 */
final readonly class WebhookPayloadData
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $eventType,
        public ?string $providerReference,
        public PaymentStatus $status,
        public ?float $amount,
        public ?string $currency,
        public array $raw,
    ) {}
}
