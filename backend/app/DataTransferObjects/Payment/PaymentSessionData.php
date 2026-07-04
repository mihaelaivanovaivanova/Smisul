<?php

namespace App\DataTransferObjects\Payment;

/**
 * What a gateway hands back after creating a payment session. Under
 * iCard's real IPG protocol, "starting a session" is not a server-to-server
 * call — it's a signed set of form fields the customer's own browser must
 * POST to actionUrl (see ICardPaymentGateway::createSession() and
 * PaymentStep/CheckoutPage on the frontend, which builds and submits that
 * form). rawResponse is whatever's safe to keep for audit — never card
 * data, since the hosted-flow model means the gateway never sends us any in
 * the first place.
 */
final readonly class PaymentSessionData
{
    /**
     * @param  array<string, string>  $formFields
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public string $actionUrl,
        public array $formFields,
        public ?string $providerReference,
        public array $rawResponse = [],
    ) {}
}
