<?php

namespace App\DataTransferObjects\Payment;

/**
 * What a gateway hands back after creating a payment session: where to
 * send the customer's browser, and (if the gateway assigns one up front)
 * its own reference for this payment. rawResponse is whatever's safe to
 * keep for audit — never card data, since the hosted-flow model means the
 * gateway never sends us any in the first place.
 */
final readonly class PaymentSessionData
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public string $redirectUrl,
        public ?string $providerReference,
        public array $rawResponse = [],
    ) {}
}
