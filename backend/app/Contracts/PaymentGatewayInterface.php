<?php

namespace App\Contracts;

use App\DataTransferObjects\Payment\PaymentSessionData;
use App\DataTransferObjects\Payment\WebhookPayloadData;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Every payment provider (iCard today; Apple Pay/Google Pay/others later —
 * see the sprint brief) implements this and nothing else touches provider
 * specifics: PaymentService only ever calls through this contract, so
 * adding a provider means a new class implementing it plus a resolution
 * rule in PaymentService, not changes scattered through the payment flow.
 *
 * No method here ever receives or returns card data — the hosted-flow
 * model this is built around means card numbers/CVV never reach this
 * application's process at all, let alone its database (see the sprint
 * brief's explicit prohibition).
 */
interface PaymentGatewayInterface
{
    public function provider(): PaymentProvider;

    /**
     * Creates the provider-side payment session/link for this Payment and
     * returns where to send the customer's browser next.
     */
    public function createSession(Payment $payment, string $returnUrl, string $cancelUrl): PaymentSessionData;

    /**
     * Verifies the webhook request actually came from the provider (see
     * each implementation for its exact scheme — HMAC signature, shared
     * secret header, etc.). Must be checked before parseWebhook() is
     * trusted for anything.
     */
    public function verifySignature(Request $request): bool;

    /**
     * Normalizes a provider-specific webhook payload into a shape
     * PaymentService understands. Only meaningful once verifySignature()
     * has passed.
     */
    public function parseWebhook(Request $request): WebhookPayloadData;

    /**
     * Queries the provider directly for a payment's current status —
     * used for reconciliation/polling rather than relying solely on
     * webhooks (which can be delayed, lost, or arrive out of order).
     */
    public function checkStatus(string $providerReference): PaymentStatus;
}
