<?php

namespace App\Contracts;

use App\DataTransferObjects\Payment\PaymentSessionData;
use App\DataTransferObjects\Payment\WebhookPayloadData;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\StoredPaymentMethod;
use Illuminate\Http\Request;

/**
 * Every payment provider (iCard today) implements this and nothing else
 * touches provider specifics: PaymentService only ever calls through this
 * contract, so adding a genuinely different provider means a new class
 * implementing it plus a resolution rule in PaymentService, not changes
 * scattered through the payment flow. Apple Pay and Google Pay are NOT
 * separate providers — they're PaymentMethod values routed through the
 * same iCard implementation, since both go through iCard's own hosted
 * checkout (see ICardPaymentGateway and docs/wallet-payments.md).
 *
 * No method here ever receives or returns card data — the hosted-flow
 * model this is built around means card numbers/CVV never reach this
 * application's process at all, let alone its database (see the sprint
 * brief's explicit prohibition).
 */
interface PaymentGatewayInterface
{
    public function environment(): string;

    public function provider(): PaymentProvider;

    /**
     * Creates the provider-side payment session for this Payment. $method
     * is the instrument the customer chose. The only gateway-backed method
     * is card; iCard renders cards and available wallets inside one modal.
     */
    public function createSession(Payment $payment, PaymentMethod $method): PaymentSessionData;

    public function createStoredCardSession(Payment $payment, StoredPaymentMethod $storedMethod): PaymentSessionData;

    /** @return array<string, mixed> */
    public function reverse(Payment $payment): array;

    /** @return array<string, mixed> */
    public function refund(Payment $payment, string $amount): array;

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
