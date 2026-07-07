<?php

namespace App\Contracts;

use App\DataTransferObjects\Payment\PaymentSessionData;
use App\DataTransferObjects\Payment\WebhookPayloadData;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Payment;
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
    public function provider(): PaymentProvider;

    /**
     * Creates the provider-side payment session for this Payment. $method
     * is the instrument the customer chose (card/apple_pay/google_pay) —
     * already validated as enabled by PaymentService before this is
     * called, so implementations don't need to re-check availability,
     * only adapt the request they build for it. Every method renders
     * entirely in-page (an embedded modal for card, a wallet SDK button
     * for Apple/Google Pay) — see PaymentSessionData.
     */
    public function createSession(Payment $payment, PaymentMethod $method): PaymentSessionData;

    /**
     * Apple Pay's merchant-validation step, driven by the wallet SDK
     * itself (not by createSession(), which returns before any wallet
     * interaction happens) — see WalletPaymentController. Returns the
     * provider's raw response, proxied back to the wallet SDK verbatim.
     *
     * @return array<string, mixed>
     */
    public function createWalletValidationSession(Payment $payment, string $merchantUrl, string $validationUrl, string $displayName): array;

    /**
     * Submits a tokenized card (from Apple/Google Pay) for the actual
     * charge, once the wallet SDK has produced one. The immediate
     * response only acknowledges receipt — the real terminal outcome
     * still only ever arrives via the async notify webhook. Returns the
     * provider's raw response, proxied back to the wallet SDK verbatim.
     *
     * @return array<string, mixed>
     */
    public function processTokenizedWalletPurchase(Payment $payment, PaymentMethod $method, string $tokenizedCard): array;

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
