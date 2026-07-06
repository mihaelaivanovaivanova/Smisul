<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\DataTransferObjects\Payment\PaymentSessionData;
use App\DataTransferObjects\Payment\WebhookPayloadData;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\Payment\InvalidWebhookPayloadException;
use App\Models\Payment;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Real iCard IPG API integration (protocol v4.5, Redirect Checkout /
 * IPGPurchase — see the "IPG API BM ECommerce" integration guide). Unlike a
 * typical hosted-page gateway, iCard does not expose a "create session" API
 * call: createSession() instead builds and RSA-signs the full IPGPurchase
 * form field set locally, and the customer's own browser POSTs it directly
 * to IPG (see PaymentSessionData — the frontend builds and submits that
 * form). Every request, response, and callback is signed/verified with an
 * RSA key pair exchanged out-of-band with iCard, not a shared secret.
 *
 * iCard's E-commerce guide documents no server-to-server status-inquiry
 * call, so checkStatus() has nothing to call — reconciliation for a
 * delayed/missing webhook relies on the return-page best-effort log only
 * (see PaymentService::reconcile()).
 *
 * Apple Pay / Google Pay ride the same IPGPurchase form-POST as card
 * payments — see walletFields() for the one (unverified) extra field
 * added for those methods, and docs/wallet-payments.md for what real
 * production setup each wallet needs beyond iCard itself.
 */
class ICardPaymentGateway implements PaymentGatewayInterface
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::ICard;
    }

    public function createSession(Payment $payment, PaymentMethod $method, string $returnUrl, string $cancelUrl): PaymentSessionData
    {
        $order = $payment->order;

        $fields = [
            'IPGmethod' => 'IPGPurchase',
            'KeyIndex' => (string) config('services.icard.key_index'),
            'KeyIndexResp' => (string) config('services.icard.key_index_resp'),
            'IPGVersion' => (string) config('services.icard.ipg_version'),
            'Language' => 'BG',
            'Originator' => (string) config('services.icard.originator'),
            'BannerIndex' => '1',
            'PostResultAction' => 'Redirect',
            'MID' => (string) config('services.icard.mid'),
            'MIDName' => (string) config('services.icard.mid_name'),
            'Amount' => number_format((float) $payment->amount, 2, '.', ''),
            'Currency' => (string) config('services.icard.currency_numeric'),
            'CustomerIP' => request()->ip() ?? '127.0.0.1',
            // Our own reference, echoed back in the callback's Payment.OrderId
            // — used as providerReference so incoming webhooks can be matched
            // back to this Payment (see parseWebhook() below).
            'OrderID' => $payment->transaction_reference,
            'CustomerIdentifier' => (string) $order->id,
            'Email' => (string) $order->customer_email,
            'URL_OK' => $returnUrl,
            'URL_Cancel' => $cancelUrl,
            'URL_Notify' => (string) config('services.icard.webhook_url'),
            // Mandatory per spec — checkout requires customer.phone, so this
            // is always present (PlaceOrderRequest::rules()).
            'MobileNumber' => (string) $order->customer_phone,
        ];

        // Recommended (not mandatory) fields — improve acceptance/fraud
        // scoring but aren't required for the request to be accepted.
        // Country is only sent when it resolves to a known ISO 3166-1
        // numeric code, since the checkout form takes free-text country
        // names rather than ISO codes.
        $shippingCountryCode = $this->isoNumericCountryCode($order->shipping_country);
        if ($shippingCountryCode !== null) {
            $fields['ShipAddrCountry'] = $shippingCountryCode;
        }
        $fields['ShipAddrCity'] = (string) $order->shipping_city;
        $fields['ShipAddrPostCode'] = (string) $order->shipping_postal_code;
        $fields['ShipAddrLine1'] = base64_encode((string) $order->shipping_address_line);

        $billingCountryCode = $this->isoNumericCountryCode($order->billing_country);
        if ($billingCountryCode !== null) {
            $fields['BillAddrCountry'] = $billingCountryCode;
        }
        $fields['BillAddrCity'] = (string) $order->billing_city;
        $fields['BillAddrPostCode'] = (string) $order->billing_postal_code;
        $fields['BillAddrLine1'] = base64_encode((string) $order->billing_address_line);

        $fields = [...$fields, ...$this->walletFields($method)];

        $fields['Signature'] = $this->sign($fields);

        $actionUrl = rtrim((string) config('services.icard.base_url'), '/').'/';

        return new PaymentSessionData(
            actionUrl: $actionUrl,
            formFields: $fields,
            providerReference: $payment->transaction_reference,
        );
    }

    /**
     * UNVERIFIED — no real iCard wallet-payment merchant account exists to
     * test this against (unlike the rest of this class's IPGPurchase field
     * set, which is confirmed against iCard's real sandbox). `PayMethod`
     * is a placeholder field name modeled on how other hosted-checkout
     * gateways restrict/pre-select a wallet on their own payment page —
     * it is NOT confirmed against iCard's actual API. Card payments never
     * call this (returns [] for PaymentMethod::Card), so this can ship
     * without any risk to the working card flow; confirm the real field
     * name/values with iCard support before enabling either wallet flag in
     * production. See docs/wallet-payments.md.
     *
     * @return array<string, string>
     */
    private function walletFields(PaymentMethod $method): array
    {
        return match ($method) {
            PaymentMethod::Card => [],
            PaymentMethod::ApplePay => ['PayMethod' => 'ApplePay'],
            PaymentMethod::GooglePay => ['PayMethod' => 'GooglePay'],
        };
    }

    public function verifySignature(Request $request): bool
    {
        $payload = $request->json()->all();

        if (! isset($payload['Signature']) || ! is_string($payload['Signature'])) {
            return false;
        }

        $signature = $payload['Signature'];
        unset($payload['Signature']);

        return $this->verify($payload, $signature);
    }

    public function parseWebhook(Request $request): WebhookPayloadData
    {
        $payload = $request->json()->all();
        $payment = $payload['Payment'] ?? null;
        $operation = $payload['Operation'] ?? null;

        if (! is_array($payment) || ! isset($payment['OrderId'], $payment['Status'])) {
            throw InvalidWebhookPayloadException::missingFields();
        }

        $sum = is_array($payment['Sum'] ?? null) ? $payment['Sum'] : null;
        $operation = is_array($operation) ? $operation : null;

        return new WebhookPayloadData(
            eventType: (string) ($operation['Type'] ?? $payment['Status']),
            providerReference: (string) $payment['OrderId'],
            status: $this->mapCallbackToStatus((string) $payment['Status'], $operation),
            amount: isset($sum['Amount']) ? (float) $sum['Amount'] : null,
            currency: isset($sum['Currency']) ? (string) $sum['Currency'] : null,
            raw: $payload,
        );
    }

    public function checkStatus(string $providerReference): PaymentStatus
    {
        throw new RuntimeException(
            "iCard's IPG Redirect Checkout API has no status-inquiry call — reconciliation for [{$providerReference}] must rely on the webhook or the customer's return-page visit.",
        );
    }

    /**
     * Payment.Status is success/error/declined, but "success" alone isn't
     * necessarily final — the same callback shape is reused for
     * intermediate steps (3DS challenge, validation) via Operation.Type.
     * Only a successful "authorization" operation confirms the payment;
     * anything else is left non-final so the order isn't touched until the
     * real terminal callback arrives.
     *
     * @param  array<string, mixed>|null  $operation
     */
    private function mapCallbackToStatus(string $paymentStatus, ?array $operation): PaymentStatus
    {
        if ($paymentStatus === 'declined' || $paymentStatus === 'error') {
            return PaymentStatus::Failed;
        }

        $operationType = $operation['Type'] ?? null;
        $operationStatus = $operation['Status'] ?? null;

        if ($operationType === 'authorization' && $operationStatus === 'success') {
            return PaymentStatus::Paid;
        }

        return PaymentStatus::Authorized;
    }

    /**
     * The checkout form collects a free-text country name, not an ISO code,
     * so BillAddrCountry/ShipAddrCountry (recommended, not mandatory) are
     * only sent when the name resolves to a known ISO 3166-1 numeric code —
     * sending a wrong code would be worse than omitting an optional field.
     * The storefront currently only ships within Bulgaria (all three
     * carriers — Econt, Speedy, BOX NOW — are Bulgaria-only).
     */
    private function isoNumericCountryCode(?string $countryName): ?string
    {
        return match (mb_strtolower(trim((string) $countryName))) {
            'bulgaria', 'българия', 'bg' => '100',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sign(array $data): string
    {
        $privateKey = openssl_pkey_get_private($this->readKeyFile((string) config('services.icard.private_key_path')));

        if ($privateKey === false) {
            throw new RuntimeException('Invalid iCard private key: '.openssl_error_string());
        }

        $dataToSign = $this->canonicalize($data);
        openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function verify(array $data, string $signatureBase64): bool
    {
        $publicKey = openssl_pkey_get_public($this->readKeyFile((string) config('services.icard.public_key_path')));

        if ($publicKey === false) {
            return false;
        }

        $decodedSignature = base64_decode($signatureBase64, strict: true);

        if ($decodedSignature === false) {
            return false;
        }

        $dataToVerify = $this->canonicalize($data);

        return openssl_verify($dataToVerify, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * iCard's signing algorithm (protocol >= 4.5): lower-case every key,
     * turn booleans into '0'/'1', flatten every value (however deeply
     * nested — request bodies are flat, callback bodies are nested
     * objects) into "parent:...:key:value" path strings, sort them in
     * natural order, and join with ";". Empty arrays are skipped entirely;
     * empty string values are kept as an empty trailing segment.
     *
     * @param  array<int|string, mixed>  $data
     */
    private function canonicalize(array $data, array $parents = []): string
    {
        $lines = $this->flatten($data, $parents);
        sort($lines, SORT_NATURAL);

        return implode(';', $lines);
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @param  array<int, string>  $parents
     * @return array<int, string>
     */
    private function flatten(array $data, array $parents): array
    {
        $lines = [];

        foreach ($data as $key => $value) {
            $keySegment = is_int($key) ? (string) $key : mb_strtolower((string) $key);

            if (is_array($value)) {
                if ($value === []) {
                    continue;
                }

                array_push($lines, ...$this->flatten($value, [...$parents, $keySegment]));

                continue;
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            $lines[] = implode(':', [...$parents, $keySegment, (string) $value]);
        }

        return $lines;
    }

    private function readKeyFile(string $path): string
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("iCard key file not found or unreadable: {$path}");
        }

        return $contents;
    }
}
