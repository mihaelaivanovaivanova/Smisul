<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\DataTransferObjects\Payment\PaymentSessionData;
use App\DataTransferObjects\Payment\WebhookPayloadData;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\Payment\InvalidWebhookPayloadException;
use App\Exceptions\Payment\PaymentGatewayException;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Real iCard IPG API integration (protocol v4.5 — see the "IPG API BM
 * ECommerce" integration guide). Every payment method renders entirely
 * in-page, never navigating the customer away from this site:
 *
 *  - Card: createModalSession() calls IPGPaymentToken server-to-server to
 *    get a short-lived Token, which the frontend uses to load iCard's own
 *    hosted JS (modalJsUrl?token=...) — that script renders a payment
 *    overlay directly on the checkout page (see PaymentSessionData and
 *    components/checkout/IcardModal.tsx).
 *  - Apple Pay / Google Pay: createWalletBootstrap() makes no iCard call
 *    at all — it just returns the config iCard's separate wallet SDK
 *    (ICardIpgGAPay) needs to render its own buttons. That SDK drives the
 *    two wallet-specific iCard calls itself, via WalletPaymentController
 *    (createWalletValidationSession() for Apple's merchant validation,
 *    processTokenizedWalletPurchase() once a wallet returns a payment
 *    token) — see components/checkout/IcardWalletButtons.tsx.
 *
 * Every request, response headers aside, is signed/verified with an RSA
 * key pair exchanged out-of-band with iCard, not a shared secret. Not
 * every response carries a Signature (IPGPaymentToken/wallet replies
 * aren't guaranteed to) — see verifyResponseSignature().
 *
 * iCard's E-commerce guide documents no server-to-server status-inquiry
 * call, so checkStatus() has nothing to call — reconciliation for a
 * delayed/missing webhook relies on the return-page best-effort log only
 * (see PaymentService::reconcile()).
 */
class ICardPaymentGateway implements PaymentGatewayInterface
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::ICard;
    }

    public function createSession(Payment $payment, PaymentMethod $method): PaymentSessionData
    {
        return match ($method) {
            PaymentMethod::Card => $this->createModalSession($payment),
            PaymentMethod::ApplePay, PaymentMethod::GooglePay => $this->createWalletBootstrap($payment),
        };
    }

    /**
     * IPGPaymentToken + ModalType=IPGPurchase: the field set is otherwise
     * identical to what a full IPGPurchase request would carry (customer/
     * address details, mandatory MobileNumber, recommended-but-optional
     * BillAddr/ShipAddr fields — see isoNumericCountryCode()), just
     * requested as a token for the embedded modal instead of a
     * browser-submitted redirect form.
     */
    private function createModalSession(Payment $payment): PaymentSessionData
    {
        $order = $payment->order;

        $fields = [
            ...$this->baseFields('IPGPaymentToken'),
            'ModalType' => 'IPGPurchase',
            'Language' => 'BG',
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
            'URL_Notify' => (string) config('services.icard.webhook_url'),
            'Note' => (string) $order->order_number,
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

        $response = $this->callIcard($fields);

        $token = $this->extractField($response, 'Token')
            ?? $this->extractField($response, 'PaymentToken')
            ?? $this->extractField($response, 'PaymentTokenID');

        if ($token === null) {
            throw PaymentGatewayException::missingField('Token');
        }

        return PaymentSessionData::modal(
            providerReference: $payment->transaction_reference,
            token: $token,
            modalJsUrl: (string) config('services.icard.modal_js_url'),
            theme: 'dark',
            rawResponse: $response,
        );
    }

    /**
     * No iCard API call — the wallet SDK (ICardIpgGAPay) itself drives
     * IPGTokenProviderSession/IPGTokenizedCardPurchase once the customer
     * actually interacts with the Apple/Google Pay button (see
     * WalletPaymentController). This just hands the frontend what it
     * needs to render those buttons in the first place.
     */
    private function createWalletBootstrap(Payment $payment): PaymentSessionData
    {
        return PaymentSessionData::wallet($payment->transaction_reference, [
            'wallet_js_url' => (string) config('services.icard.wallet_js_url'),
            'environment' => config('services.icard.environment') === 'production' ? 'prod' : 'sandbox',
            'mid' => (string) config('services.icard.mid'),
            'mid_name' => (string) config('services.icard.mid_name'),
            'currency_alpha' => (string) $payment->currency,
            'apple_merchant_domain' => config('services.apple_pay.merchant_domain'),
            'google_merchant_id' => config('services.google_pay.merchant_id'),
        ]);
    }

    /**
     * Apple Pay's merchant-validation step: the frontend's onvalidatemerchant
     * callback needs iCard's raw IPGTokenProviderSession response (Apple's
     * own merchant session object), so this proxies it back verbatim
     * rather than reshaping it.
     *
     * @return array<string, mixed>
     */
    public function createWalletValidationSession(Payment $payment, string $merchantUrl, string $validationUrl, string $displayName): array
    {
        return $this->callIcard([
            ...$this->baseFields('IPGTokenProviderSession'),
            'OrderID' => $payment->transaction_reference,
            'MerchantUrl' => $merchantUrl,
            'ValidationURL' => $validationUrl,
            'DisplayName' => $displayName,
            'TokenizedCardProvider' => 'Apple',
        ]);
    }

    /**
     * Once Apple/Google Pay hands back a tokenized card, this submits it
     * to iCard for the actual charge. The immediate response only
     * acknowledges receipt (Status "0" = accepted for processing) — the
     * real terminal outcome still only ever arrives via the async notify
     * webhook, exactly like the card modal flow (see
     * PaymentService::handleWebhook). Response is proxied back verbatim,
     * same reasoning as createWalletValidationSession().
     *
     * @return array<string, mixed>
     */
    public function processTokenizedWalletPurchase(Payment $payment, PaymentMethod $method, string $tokenizedCard): array
    {
        $order = $payment->order;

        return $this->callIcard([
            ...$this->baseFields('IPGTokenizedCardPurchase'),
            'OrderID' => $payment->transaction_reference,
            'Email' => (string) $order->customer_email,
            'CustomerIdentifier' => (string) $order->id,
            'Amount' => number_format((float) $payment->amount, 2, '.', ''),
            'Currency' => (string) config('services.icard.currency_numeric'),
            'URL_Notify' => (string) config('services.icard.webhook_url'),
            'TokenizedCardProvider' => $method === PaymentMethod::GooglePay ? 'Google' : 'Apple',
            'TokenizedCard' => $tokenizedCard,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function baseFields(string $ipgMethod): array
    {
        return [
            'IPGmethod' => $ipgMethod,
            'KeyIndex' => (string) config('services.icard.key_index'),
            'KeyIndexResp' => (string) config('services.icard.key_index_resp'),
            'IPGVersion' => (string) config('services.icard.ipg_version'),
            'Originator' => (string) config('services.icard.originator'),
            'OutputFormat' => 'json',
            'MID' => (string) config('services.icard.mid'),
        ];
    }

    /**
     * Signs $fields and POSTs them to iCard's IPG API, returning the
     * parsed response. Every server-to-server call (modal token creation,
     * wallet validation session, tokenized purchase) goes through this
     * single path rather than three near-identical copies.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function callIcard(array $fields): array
    {
        $fields['Signature'] = $this->sign($fields);

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post(rtrim((string) config('services.icard.base_url'), '/').'/', $fields);
        } catch (ConnectionException $exception) {
            throw PaymentGatewayException::requestFailed($exception->getMessage());
        }

        if ($response->failed()) {
            throw PaymentGatewayException::requestFailed("HTTP {$response->status()}");
        }

        $parsed = $this->parseIcardResponse($response->body());

        if (! $this->verifyResponseSignature($parsed)) {
            throw PaymentGatewayException::requestFailed('response signature verification failed');
        }

        return $parsed;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseIcardResponse(string $body): array
    {
        $decoded = json_decode($body, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        parse_str($body, $parsed);

        return $parsed;
    }

    /**
     * Not every iCard API response is signed (IPGPaymentToken/wallet
     * replies aren't guaranteed to be) — absence of a Signature key means
     * there's nothing to verify, not that verification failed. This is
     * separate from verifySignature() below, which verifies the async
     * notify webhook and is always mandatory.
     *
     * @param  array<int|string, mixed>  $response
     */
    private function verifyResponseSignature(array $response): bool
    {
        $signature = null;
        $withoutSignature = [];

        foreach ($response as $key => $value) {
            if (is_string($key) && strcasecmp($key, 'signature') === 0) {
                $signature = $value;
            } else {
                $withoutSignature[$key] = $value;
            }
        }

        if ($signature === null) {
            return true;
        }

        return $this->verify($withoutSignature, (string) $signature);
    }

    /**
     * @param  array<int|string, mixed>  $response
     */
    private function extractField(array $response, string $key): ?string
    {
        foreach ($response as $candidate => $value) {
            if (is_string($candidate) && strcasecmp($candidate, $key) === 0) {
                return $value === null ? null : (string) $value;
            }
        }

        return null;
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
            "iCard's IPG API has no status-inquiry call — reconciliation for [{$providerReference}] must rely on the webhook or the customer's return-page visit.",
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
