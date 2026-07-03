<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\DataTransferObjects\Payment\PaymentSessionData;
use App\DataTransferObjects\Payment\WebhookPayloadData;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\Payment\InvalidWebhookPayloadException;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A hosted-payment-page integration: createSession() builds a signed
 * redirect URL locally (no card data is ever collected or transmitted by
 * this application — the customer enters their card details on iCard's own
 * page) rather than calling out to an API to open a session first, which
 * matches how most redirect-based card gateways in this region actually
 * work. checkStatus() is the one genuine outbound call this class makes,
 * used for reconciliation rather than the primary flow (which relies on
 * the webhook).
 *
 * No real iCard API documentation exists yet — the URL shape, webhook
 * payload shape, and signature scheme below are a reasonable placeholder
 * built to match this contract, and will need adjusting to iCard's actual
 * integration guide before this can talk to a real sandbox (see the sprint
 * report's "what needs real credentials" section).
 */
class ICardPaymentGateway implements PaymentGatewayInterface
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::ICard;
    }

    public function createSession(Payment $payment, string $returnUrl, string $cancelUrl): PaymentSessionData
    {
        $merchantId = (string) config('services.icard.merchant_id');
        $baseUrl = rtrim((string) config('services.icard.base_url'), '/');

        $params = [
            'merchant_id' => $merchantId,
            'reference' => $payment->transaction_reference,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => $payment->currency,
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
        ];

        $params['signature'] = $this->sign($params);

        $redirectUrl = "{$baseUrl}/pay?".http_build_query($params);

        return new PaymentSessionData(
            redirectUrl: $redirectUrl,
            providerReference: $payment->transaction_reference,
            rawResponse: ['request' => $params],
        );
    }

    public function verifySignature(Request $request): bool
    {
        $secret = (string) config('services.icard.secret');
        $provided = (string) $request->header('X-ICard-Signature', '');

        if ($provided === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $provided);
    }

    public function parseWebhook(Request $request): WebhookPayloadData
    {
        $payload = $request->json()->all();

        if (! isset($payload['event'], $payload['reference'])) {
            throw InvalidWebhookPayloadException::missingFields();
        }

        return new WebhookPayloadData(
            eventType: (string) $payload['event'],
            providerReference: (string) $payload['reference'],
            status: $this->mapEventToStatus((string) $payload['event']),
            amount: isset($payload['amount']) ? (float) $payload['amount'] : null,
            currency: isset($payload['currency']) ? (string) $payload['currency'] : null,
            raw: $payload,
        );
    }

    public function checkStatus(string $providerReference): PaymentStatus
    {
        $baseUrl = rtrim((string) config('services.icard.base_url'), '/');
        $secret = (string) config('services.icard.secret');

        $response = Http::withToken($secret)
            ->acceptJson()
            ->timeout(5)
            ->get("{$baseUrl}/payments/{$providerReference}");

        if ($response->failed()) {
            throw new RuntimeException("iCard status check failed for reference [{$providerReference}]: HTTP {$response->status()}");
        }

        $status = (string) $response->json('status');

        return $this->mapEventToStatus($status);
    }

    /**
     * @param  array<string, string>  $params
     */
    private function sign(array $params): string
    {
        $secret = (string) config('services.icard.secret');
        ksort($params);

        return hash_hmac('sha256', http_build_query($params), $secret);
    }

    private function mapEventToStatus(string $event): PaymentStatus
    {
        return match (true) {
            str_contains($event, 'authorized') => PaymentStatus::Authorized,
            str_contains($event, 'paid'), str_contains($event, 'success') => PaymentStatus::Paid,
            str_contains($event, 'failed') => PaymentStatus::Failed,
            str_contains($event, 'cancelled'), str_contains($event, 'canceled') => PaymentStatus::Cancelled,
            str_contains($event, 'expired') => PaymentStatus::Expired,
            str_contains($event, 'refunded') => PaymentStatus::Refunded,
            default => PaymentStatus::Pending,
        };
    }
}
