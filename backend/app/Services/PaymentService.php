<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\Payment\InvalidPaymentMethodException;
use App\Exceptions\Payment\InvalidWebhookSignatureException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\StoredPaymentMethod;
use App\Models\User;
use App\Services\Payments\ICardConfigurationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
        private readonly ICardConfigurationService $icardConfiguration,
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
    public function initiate(Order $order, PaymentMethod $method = PaymentMethod::Card, ?int $storedPaymentMethodId = null): Payment
    {
        $this->assertMethodEnabled($method);

        return DB::transaction(function () use ($order, $method, $storedPaymentMethodId) {
            $storedMethod = null;
            if ($storedPaymentMethodId !== null) {
                if ($method !== PaymentMethod::Card || $order->user_id === null) {
                    throw new RuntimeException('Stored cards can only be used by an authenticated customer paying by card.');
                }
                $storedMethod = StoredPaymentMethod::query()
                    ->whereKey($storedPaymentMethodId)
                    ->where('user_id', $order->user_id)
                    ->where('is_active', true)
                    ->firstOrFail();
            }

            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => $this->gateway->provider(),
                'gateway_environment' => $this->gateway->environment(),
                'payment_method' => $method,
                'status' => PaymentStatus::Pending,
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'transaction_reference' => $this->icardOrderId($order->order_number),
            ]);

            $session = $storedMethod !== null
                ? $this->gateway->createStoredCardSession($payment, $storedMethod)
                : $this->gateway->createSession($payment, $method);

            $sessionData = $session->mode === 'modal'
                ? ['modal_token' => $session->modalToken, 'modal_js_url' => $session->modalJsUrl, 'theme' => $session->theme]
                : ['wallet' => $session->walletConfig];

            $safeGatewayResponse = $this->redactSensitivePaymentData($session->rawResponse);

            $payment->update([
                'status' => PaymentStatus::Initiated,
                'provider_reference' => $session->providerReference,
                'raw_response' => $sessionData + $safeGatewayResponse,
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
     * The methods a payment can actually be placed with right now — one
     * hosted iCard method, everywhere, regardless of carrier. Cash on
     * delivery used to widen this for BOX NOW orders; see
     * PaymentMethod::active() for why it's gone.
     *
     * This is the single source of truth PlaceOrderRequest's validation,
     * PaymentController's enabled-methods check, and checkout's method list
     * (CheckoutController::paymentMethods()) all read from.
     *
     * @return list<PaymentMethod>
     */
    public function availablePaymentMethods(): array
    {
        return PaymentMethod::active();
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

    private function icardOrderId(string $orderNumber): string
    {
        $safe = preg_replace('/[^A-Za-z0-9-]/', '', $orderNumber) ?: 'SMS';
        $suffix = base_convert((string) time(), 10, 36).substr(bin2hex(random_bytes(3)), 0, 6);

        // payments.transaction_reference is CHAR(36) on existing installs.
        return substr($safe.'-'.$suffix, 0, 36);
    }

    public function latestForOrder(Order $order): ?Payment
    {
        return Payment::where('order_id', $order->id)->latest()->first();
    }

    /** @return Collection<int, StoredPaymentMethod> */
    public function storedMethodsFor(User $user)
    {
        return $user->storedPaymentMethods()->where('is_active', true)->latest()->get();
    }

    public function removeStoredMethod(User $user, StoredPaymentMethod $method): void
    {
        abort_unless($method->user_id === $user->id, 404);
        $method->update(['is_active' => false]);
    }

    public function reverse(Payment $payment, User $administrator): Payment
    {
        if ($payment->provider !== PaymentProvider::ICard) {
            throw ValidationException::withMessages(['payment' => 'Only iCard payments can be reversed.']);
        }

        if (! in_array($payment->status, [PaymentStatus::Authorized, PaymentStatus::Paid], true)
            || $payment->reversed_at !== null
            || bccomp((string) $payment->refunded_amount, '0', 2) > 0) {
            throw ValidationException::withMessages(['payment' => 'Only a non-reversed authorized/paid payment can be reversed.']);
        }
        if (! in_array($payment->order->status, [OrderStatus::AwaitingPayment, OrderStatus::Paid], true)) {
            throw ValidationException::withMessages(['payment' => 'A reversal is only allowed before order processing starts. Use refund instead.']);
        }
        if (! filled($payment->gateway_transaction_reference)) {
            throw ValidationException::withMessages(['payment' => 'The iCard transaction reference has not arrived in a verified callback yet.']);
        }
        $response = $this->gateway->reverse($payment);

        return DB::transaction(function () use ($payment, $administrator, $response) {
            $payment->update(['status' => PaymentStatus::Cancelled, 'reversed_at' => now(), 'completed_at' => now()]);
            $payment->transactions()->create([
                'type' => 'reversal', 'payment_method' => $payment->payment_method,
                'status' => PaymentStatus::Cancelled, 'raw_payload' => $this->redactSensitivePaymentData($response),
            ]);
            if (in_array($payment->order->status, [OrderStatus::Paid, OrderStatus::AwaitingPayment], true)) {
                $this->orderStatus->transitionTo($payment->order, OrderStatus::Cancelled, $administrator, 'iCard payment reversed');
            }

            return $payment->fresh();
        });
    }

    public function refund(Payment $payment, string $amount, User $administrator): Payment
    {
        if ($payment->provider !== PaymentProvider::ICard) {
            throw ValidationException::withMessages(['payment' => 'Only iCard payments can be refunded.']);
        }

        if ($payment->status !== PaymentStatus::Paid) {
            throw ValidationException::withMessages(['payment' => 'Only a paid payment can be refunded.']);
        }
        if (! filled($payment->gateway_transaction_reference)) {
            throw ValidationException::withMessages(['payment' => 'The iCard transaction reference has not arrived in a verified callback yet.']);
        }
        $remaining = bcsub((string) $payment->amount, (string) $payment->refunded_amount, 2);
        if (bccomp($amount, '0', 2) <= 0 || bccomp($amount, $remaining, 2) > 0) {
            throw ValidationException::withMessages(['amount' => "Refund amount must be between 0.01 and {$remaining}."]);
        }
        $response = $this->gateway->refund($payment, number_format((float) $amount, 2, '.', ''));

        return DB::transaction(function () use ($payment, $amount, $remaining, $administrator, $response) {
            $full = bccomp($amount, $remaining, 2) === 0;
            $payment->update([
                'refunded_amount' => bcadd((string) $payment->refunded_amount, $amount, 2),
                'refunded_at' => now(),
                'status' => $full ? PaymentStatus::Refunded : PaymentStatus::Paid,
            ]);
            $payment->transactions()->create([
                'type' => 'refund', 'payment_method' => $payment->payment_method,
                'status' => $full ? PaymentStatus::Refunded : PaymentStatus::Paid,
                'raw_payload' => $this->redactSensitivePaymentData($response) + ['requested_amount' => $amount],
            ]);
            if ($full && $payment->order->status !== OrderStatus::Refunded) {
                $this->orderStatus->transitionTo($payment->order, OrderStatus::Refunded, $administrator, 'iCard payment fully refunded');
            }

            return $payment->fresh();
        });
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

        $existingDelivery = PaymentWebhookLog::where('idempotency_key', $idempotencyKey)->first();
        if ($existingDelivery !== null) {
            if (! $existingDelivery->signature_valid) {
                throw InvalidWebhookSignatureException::create();
            }

            return;
        }

        $signatureValid = $this->gateway->verifySignature($request);
        $rawPayload = $request->json()->all();
        if ($rawPayload === []) {
            $rawPayload = $request->all();
        }

        if (! $signatureValid) {
            PaymentWebhookLog::create([
                'payment_id' => null,
                'provider' => $this->gateway->provider(),
                'event_type' => $this->payloadValue($rawPayload, ['Operation', 'Type'])
                    ?? $this->payloadValue($rawPayload, ['Payment', 'Status']),
                'provider_reference' => $this->payloadValue($rawPayload, ['Payment', 'OrderId']),
                'idempotency_key' => $idempotencyKey,
                'signature_valid' => false,
                'error_message' => 'Invalid iCard callback signature or source IP.',
                'payload' => $this->redactSensitivePaymentData($rawPayload),
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
            'error_message' => $payment === null ? 'No payment matched the iCard callback OrderID.' : null,
            'payload' => $this->redactSensitivePaymentData($webhookData->raw),
            'processed_at' => now(),
        ]);

        if ($payment === null) {
            Log::warning('iCard webhook received for unknown payment reference', ['reference' => $webhookData->providerReference]);

            return;
        }

        $providerTrn = $this->payloadValue($webhookData->raw, ['Operation', 'Provider', 'Trn']);
        if (is_scalar($providerTrn) && (string) $providerTrn !== '') {
            $payment->update(['gateway_transaction_reference' => (string) $providerTrn]);
        }

        $this->captureStoredCard($payment, $webhookData->raw);

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
                'raw_payload' => $this->redactSensitivePaymentData($webhookData->raw) + ['_rejected_reason' => 'amount_mismatch'],
            ]);

            Log::error('iCard webhook amount mismatch — payment not updated', [
                'payment_id' => $payment->id,
                'expected' => (string) $payment->amount,
                'received' => $webhookData->amount,
            ]);

            return;
        }

        $expectedCurrency = (string) ($this->icardConfiguration->active($payment->gateway_environment)['currency_numeric'] ?? '');
        if ($webhookData->currency !== null && $expectedCurrency !== '' && $webhookData->currency !== $expectedCurrency) {
            Log::error('iCard webhook currency mismatch — payment not updated', [
                'payment_id' => $payment->id, 'expected' => $expectedCurrency, 'received' => $webhookData->currency,
            ]);

            return;
        }

        DB::transaction(function () use ($payment, $webhookData) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            $payment->update([
                'status' => $webhookData->status,
                'completed_at' => $webhookData->status->isFinal() ? now() : null,
                'paid_at' => $webhookData->status === PaymentStatus::Paid
                    ? ($payment->paid_at ?? now())
                    : $payment->paid_at,
                'operation_status' => $this->scalarPayloadValue($webhookData->raw, ['Operation', 'Status']),
                'operation_code' => $this->scalarPayloadValue($webhookData->raw, ['Operation', 'Code']),
                'operation_message' => $this->scalarPayloadValue($webhookData->raw, ['Operation', 'Message']),
                'approval_code' => $this->scalarPayloadValue($webhookData->raw, ['Payment', 'ApprovalCode']),
                'masked_pan' => $this->maskPan($this->scalarPayloadValue($webhookData->raw, ['CardData', 'Pan'])),
                'card_type' => $this->scalarPayloadValue($webhookData->raw, ['CardData', 'Type']),
                'cardholder_name' => $this->scalarPayloadValue($webhookData->raw, ['CardData', 'CardholderName']),
            ]);

            $payment->transactions()->create([
                'type' => 'webhook',
                'payment_method' => $payment->payment_method,
                'status' => $webhookData->status,
                'raw_payload' => $this->redactSensitivePaymentData($webhookData->raw),
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

    /** @param array<string, mixed> $payload */
    private function captureStoredCard(Payment $payment, array $payload): void
    {
        $token = $this->payloadValue($payload, ['Operation', 'StoreCard', 'CardToken']);
        if ($token === null && mb_strtolower((string) $this->payloadValue($payload, ['Operation', 'Type'])) === 'store_card') {
            $token = $this->payloadValue($payload, ['CardData', 'CardToken']);
        }
        if (! is_string($token) || $token === '' || $payment->order->user_id === null) {
            return;
        }

        $pan = (string) ($this->payloadValue($payload, ['CardData', 'Pan']) ?? '');
        preg_match('/(\d{4})$/', $pan, $matches);
        StoredPaymentMethod::query()->updateOrCreate(
            ['token_hash' => hash('sha256', $token)],
            [
                'user_id' => $payment->order->user_id,
                'provider' => 'icard',
                'card_token' => $token,
                'brand' => $this->payloadValue($payload, ['CardData', 'Type']),
                'last_four' => $matches[1] ?? null,
                'expiry_month' => null,
                'expiry_year' => null,
                'is_active' => true,
            ],
        );
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function redactSensitivePaymentData(array $payload): array
    {
        foreach ($payload as $key => &$value) {
            $normalized = mb_strtolower((string) $key);
            if (in_array($normalized, ['signature', 'tokenizedcard', 'cardtoken', 'cvv', 'cvc', 'expmonth', 'expyear', 'expiry', 'expiration'], true)) {
                $value = '[REDACTED]';
            } elseif (in_array($normalized, ['pan', 'cardnumber'], true) && is_scalar($value)) {
                $value = $this->maskPan((string) $value);
            } elseif (is_array($value)) {
                $value = $this->redactSensitivePaymentData($value);
            }
        }
        unset($value);

        return $payload;
    }

    /** @param array<int|string, mixed> $payload @param list<string> $path */
    private function payloadValue(array $payload, array $path): mixed
    {
        $value = $payload;
        foreach ($path as $segment) {
            if (! is_array($value)) {
                return null;
            }
            $found = false;
            foreach ($value as $key => $child) {
                if (strcasecmp((string) $key, $segment) !== 0) {
                    continue;
                }
                $value = $child;
                $found = true;
                break;
            }
            if (! $found) {
                return null;
            }
        }

        return $value;
    }

    /** @param array<int|string, mixed> $payload @param list<string> $path */
    private function scalarPayloadValue(array $payload, array $path): ?string
    {
        $value = $this->payloadValue($payload, $path);

        return is_scalar($value) ? (string) $value : null;
    }

    private function maskPan(?string $pan): ?string
    {
        if ($pan === null || trim($pan) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $pan) ?? '';
        if (strlen($digits) < 4) {
            return '[REDACTED]';
        }

        return str_repeat('*', max(4, strlen($digits) - 4)).substr($digits, -4);
    }
}
