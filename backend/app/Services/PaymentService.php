<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\Payment\InvalidPaymentMethodException;
use App\Exceptions\Payment\InvalidWebhookSignatureException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Orchestrates the payment side of an order: creating a gateway session,
 * recording every interaction (PaymentTransaction) and webhook delivery
 * (PaymentWebhookLog) for audit, and translating the outcome into the
 * order-status transitions OrderService/OrderStatusService already know
 * how to make. This class never talks to a gateway directly — everything
 * provider-specific goes through the injected PaymentGatewayInterface (see
 * PaymentServiceProvider for the iCard binding), so a second provider is
 * an interface implementation and a resolution rule here, not a rewrite.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly OrderService $orders,
        private readonly OrderStatusService $orderStatus,
    ) {}

    /**
     * Always mints a fresh attempt (new transaction_reference, freshly
     * signed gateway fields) rather than reusing a prior non-final payment.
     * iCard's IPG API keys duplicate-transmission rejection off MID+OrderID
     * (our transaction_reference) — resubmitting the same OrderID a second
     * time, which is exactly what a "retry" after Failed/Cancelled does, is
     * rejected by iCard itself ("payment process interrupted"), so an
     * order-level idempotency shortcut here would be actively wrong for its
     * own purpose. The old, already-submitted Payment row is simply left
     * behind as a non-final, never-confirmed attempt — harmless audit
     * clutter, not a live session anything still points at.
     */
    public function initiate(Order $order, PaymentMethod $method = PaymentMethod::Card): Payment
    {
        $this->assertMethodEnabled($method);

        return DB::transaction(function () use ($order, $method) {
            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => $this->gateway->provider(),
                'payment_method' => $method,
                'status' => PaymentStatus::Pending,
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'transaction_reference' => (string) Str::uuid(),
            ]);

            $session = $this->gateway->createSession($payment, $method);

            $sessionData = $session->mode === 'modal'
                ? ['modal_token' => $session->modalToken, 'modal_js_url' => $session->modalJsUrl, 'theme' => $session->theme]
                : ['wallet' => $session->walletConfig];

            $payment->update([
                'status' => PaymentStatus::Initiated,
                'provider_reference' => $session->providerReference,
                'raw_response' => $sessionData + $session->rawResponse,
                'initiated_at' => now(),
            ]);

            $payment->transactions()->create([
                'type' => 'initiated',
                'payment_method' => $method,
                'status' => PaymentStatus::Initiated,
                'raw_payload' => $sessionData,
            ]);

            if ($order->status === OrderStatus::Pending) {
                $this->orderStatus->transitionTo($order, OrderStatus::AwaitingPayment, changedBy: null, note: 'Payment initiated');
            }

            return $payment->fresh();
        });
    }

    /**
     * The methods available to offer at checkout right now — Card is
     * always available; Apple Pay/Google Pay only appear once both their
     * app-level flag (apple_pay.enabled/google_pay.enabled) and iCard's
     * own wallet flag (icard.apple_pay_enabled/icard.google_pay_enabled)
     * are on. See config/services.php for why there are two flags per
     * wallet.
     *
     * @return list<PaymentMethod>
     */
    public function availablePaymentMethods(): array
    {
        $methods = [PaymentMethod::Card];

        if (config('services.apple_pay.enabled') && config('services.icard.apple_pay_enabled')) {
            $methods[] = PaymentMethod::ApplePay;
        }

        if (config('services.google_pay.enabled') && config('services.icard.google_pay_enabled')) {
            $methods[] = PaymentMethod::GooglePay;
        }

        return $methods;
    }

    /**
     * Defense in depth beyond the FormRequest validation layer (see
     * PlaceOrderRequest/InitiatePaymentRequest) — initiate() is a public
     * method any future caller could reach directly with a method that
     * was never checked against current config.
     */
    private function assertMethodEnabled(PaymentMethod $method): void
    {
        if (! in_array($method, $this->availablePaymentMethods(), strict: true)) {
            throw InvalidPaymentMethodException::disabled($method->value);
        }
    }

    public function latestForOrder(Order $order): ?Payment
    {
        return Payment::where('order_id', $order->id)->latest()->first();
    }

    /**
     * Records that the customer's browser came back from the gateway
     * (success or failure branch) and opportunistically reconciles via
     * checkStatus() in case the webhook hasn't arrived yet — this is
     * never the authoritative state change on its own (a customer could
     * edit the URL they land on), only ever a trigger to go check.
     */
    public function recordReturn(Payment $payment): Payment
    {
        $payment->transactions()->create([
            'type' => 'return',
            'payment_method' => $payment->payment_method,
            'status' => $payment->status,
            'raw_payload' => null,
        ]);

        return $this->reconcile($payment);
    }

    /**
     * Cancels a payment/order combination that hasn't reached a final
     * state yet — used both when the customer is redirected back from
     * iCard's own cancel flow and for a customer-initiated "cancel"
     * action while still on the checkout/payment step.
     */
    public function cancel(Order $order, ?User $cancelledBy = null): Payment
    {
        $payment = $this->latestForOrder($order);

        return DB::transaction(function () use ($order, $payment, $cancelledBy) {
            if ($payment !== null && ! $payment->status->isFinal()) {
                $payment->update(['status' => PaymentStatus::Cancelled, 'completed_at' => now()]);
                $payment->transactions()->create([
                    'type' => 'cancel_return',
                    'payment_method' => $payment->payment_method,
                    'status' => PaymentStatus::Cancelled,
                    'raw_payload' => null,
                ]);
            }

            $this->orders->cancel($order, $cancelledBy, 'Payment cancelled');

            return $payment ?? throw new RuntimeException("Order {$order->order_number} has no payment to cancel.");
        });
    }

    /**
     * Queries the gateway directly for a payment's current status and
     * applies it exactly like a webhook would — the reconciliation path
     * for when a webhook is delayed or never arrives at all. Best-effort:
     * if the gateway can't be reached, this quietly leaves the payment as
     * it was rather than failing the customer-facing return page — the
     * webhook remains the authoritative path and may still arrive.
     */
    public function reconcile(Payment $payment): Payment
    {
        if ($payment->status->isFinal() || $payment->provider_reference === null) {
            return $payment;
        }

        try {
            $status = $this->gateway->checkStatus($payment->provider_reference);
        } catch (Throwable $exception) {
            Log::warning('iCard status reconciliation failed', [
                'payment_id' => $payment->id,
                'error' => $exception->getMessage(),
            ]);

            return $payment;
        }

        if ($status === $payment->status) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $status) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first() ?? $payment;

            $payment->update([
                'status' => $status,
                'completed_at' => $status->isFinal() ? now() : null,
            ]);

            $payment->transactions()->create([
                'type' => 'status_check',
                'payment_method' => $payment->payment_method,
                'status' => $status,
                'raw_payload' => null,
            ]);

            $this->applyOrderTransition($payment->order, $status, 'Reconciled via status check');

            return $payment;
        });
    }

    /**
     * The webhook entry point: verifies the signature, deduplicates by an
     * idempotency key derived from the raw body (so a retried delivery of
     * the exact same payload is a detectable no-op), and only then applies
     * the status to the payment/order. Every delivery is logged —
     * including ones that fail signature verification or that can't be
     * matched to a known payment — since PaymentWebhookLog is the audit
     * trail of record for "what did the gateway ever tell us", not just
     * "what did we act on".
     */
    public function handleWebhook(Request $request): void
    {
        $idempotencyKey = hash('sha256', $request->getContent());

        if (PaymentWebhookLog::where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }

        $signatureValid = $this->gateway->verifySignature($request);
        $rawPayload = $request->json()->all();

        if (! $signatureValid) {
            PaymentWebhookLog::create([
                'payment_id' => null,
                'provider' => $this->gateway->provider(),
                'event_type' => $rawPayload['Operation']['Type'] ?? $rawPayload['Payment']['Status'] ?? null,
                'provider_reference' => $rawPayload['Payment']['OrderId'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'signature_valid' => false,
                'payload' => $rawPayload,
            ]);

            throw InvalidWebhookSignatureException::create();
        }

        $webhookData = $this->gateway->parseWebhook($request);
        $payment = $webhookData->providerReference !== null
            ? Payment::where('provider_reference', $webhookData->providerReference)->first()
            : null;

        PaymentWebhookLog::create([
            'payment_id' => $payment?->id,
            'provider' => $this->gateway->provider(),
            'event_type' => $webhookData->eventType,
            'provider_reference' => $webhookData->providerReference,
            'idempotency_key' => $idempotencyKey,
            'signature_valid' => true,
            'payload' => $webhookData->raw,
            'processed_at' => now(),
        ]);

        if ($payment === null) {
            Log::warning('iCard webhook received for unknown payment reference', ['reference' => $webhookData->providerReference]);

            return;
        }

        if ($payment->status->isFinal()) {
            return;
        }

        // Amount/currency tampering guard: a webhook claiming a different
        // amount than what this payment was actually created for is never
        // trusted to confirm it, regardless of what status it reports.
        if ($webhookData->amount !== null && bccomp((string) $webhookData->amount, (string) $payment->amount, 2) !== 0) {
            $payment->transactions()->create([
                'type' => 'webhook',
                'payment_method' => $payment->payment_method,
                'status' => $payment->status,
                'raw_payload' => $webhookData->raw + ['_rejected_reason' => 'amount_mismatch'],
            ]);

            Log::error('iCard webhook amount mismatch — payment not updated', [
                'payment_id' => $payment->id,
                'expected' => (string) $payment->amount,
                'received' => $webhookData->amount,
            ]);

            return;
        }

        DB::transaction(function () use ($payment, $webhookData) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            $payment->update([
                'status' => $webhookData->status,
                'completed_at' => $webhookData->status->isFinal() ? now() : null,
            ]);

            $payment->transactions()->create([
                'type' => 'webhook',
                'payment_method' => $payment->payment_method,
                'status' => $webhookData->status,
                'raw_payload' => $webhookData->raw,
            ]);

            $this->applyOrderTransition($payment->order, $webhookData->status, 'Confirmed via iCard webhook');
        });
    }

    private function applyOrderTransition(Order $order, PaymentStatus $status, string $note): void
    {
        match ($status) {
            PaymentStatus::Paid => $this->orders->confirmPayment($order, $note),
            PaymentStatus::Failed => $this->orderStatus->transitionTo($order, OrderStatus::Failed, changedBy: null, note: $note),
            PaymentStatus::Cancelled, PaymentStatus::Expired => $this->orders->cancel($order, null, $note),
            default => null,
        };
    }

    /**
     * Apple Pay's merchant-validation step, called by the frontend's
     * wallet SDK (ICardIpgGAPay) itself via WalletPaymentController — not
     * part of initiate()/createSession(), which returns before any wallet
     * interaction happens. Doesn't touch payment.status: this is just
     * merchant validation, not a charge.
     *
     * @return array<string, mixed>
     */
    public function createWalletValidationSession(Payment $payment, string $merchantUrl, string $validationUrl, string $displayName): array
    {
        $response = $this->gateway->createWalletValidationSession($payment, $merchantUrl, $validationUrl, $displayName);

        $payment->transactions()->create([
            'type' => 'wallet_validation_session',
            'payment_method' => $payment->payment_method,
            'status' => $payment->status,
            'raw_payload' => $response,
        ]);

        return $response;
    }

    /**
     * Submits the tokenized card the wallet SDK produced. The response
     * only acknowledges receipt — final status still only ever comes from
     * the async notify webhook (see handleWebhook()), exactly like the
     * card modal flow, so payment.status is deliberately left untouched
     * here.
     *
     * @return array<string, mixed>
     */
    public function processTokenizedWalletPurchase(Payment $payment, PaymentMethod $method, string $tokenizedCard): array
    {
        $response = $this->gateway->processTokenizedWalletPurchase($payment, $method, $tokenizedCard);

        $payment->transactions()->create([
            'type' => 'wallet_purchase_ack',
            'payment_method' => $payment->payment_method,
            'status' => $payment->status,
            'raw_payload' => $response,
        ]);

        return $response;
    }
}
