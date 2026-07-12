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
use App\Models\StoredPaymentMethod;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Real iCard IPG API integration (protocol v4.5 — see the "IPG API BM
 * ECommerce" integration guide). Card is the only method with its own
 * flow today:
 *
 *  - Card: createModalSession() calls IPGPaymentToken server-to-server to
 *    get a short-lived Token, which the frontend uses to load iCard's own
 *    hosted JS (modalJsUrl?token=...) — that script renders a payment
 *    overlay directly on the checkout page (see PaymentSessionData and
 *    components/checkout/IcardModal.tsx).
 *  - Apple Pay / Google Pay: not offered as separate checkout options
 *    (see PaymentMethod's docblock) — createSession() throws for them.
 *    A prior separate wallet-SDK integration (ICardIpgGAPay button flow,
 *    its own WalletPaymentController/tokenProviderSession/
 *    tokenizedCardPurchase endpoints) existed at one point but was removed;
 *    if wallets are reintroduced, they may instead be offered inside the
 *    card modal itself rather than as standalone buttons.
 *
 * Every request, response headers aside, is signed/verified with an RSA
 * key pair exchanged out-of-band with iCard, not a shared secret. Protocol
 * 4.5 response signatures are verified before any response is trusted.
 *
 * iCard's E-commerce guide documents no server-to-server status-inquiry
 * call, so checkStatus() has nothing to call — reconciliation for a
 * delayed/missing webhook relies on the return-page best-effort log only
 * (see PaymentService::reconcile()).
 */
class ICardPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly ICardConfigurationService $configuration) {}

    public function provider(): PaymentProvider
    {
        return PaymentProvider::ICard;
    }

    public function environment(): string
    {
        return $this->configuration->activeEnvironment();
    }

    public function createSession(Payment $payment, PaymentMethod $method): PaymentSessionData
    {
        return match ($method) {
            PaymentMethod::Card => $this->createModalSession($payment),
            PaymentMethod::ApplePay, PaymentMethod::GooglePay, PaymentMethod::CashOnDelivery => throw PaymentGatewayException::requestFailed('this checkout method does not use a separate iCard flow'),
        };
    }

    /**
     * IPGPaymentToken + ModalType=IPGPurchase: the field set is otherwise
     * identical to what a full IPGPurchase request would carry (customer/
     * address details, mandatory MobileNumber, recommended-but-optional
     * BillAddr/ShipAddr fields — hardcoded to Bulgaria's ISO 3166-1 numeric
     * code below since every carrier this storefront ships with is
     * Bulgaria-only), just requested as a token for the embedded modal
     * instead of a browser-submitted redirect form.
     */
    private function createModalSession(Payment $payment): PaymentSessionData
    {
        $environment = $payment->gateway_environment ?: $this->environment();
        $config = $this->configuration->assertReady($environment);
        $order = $payment->order;

        $fields = [
            ...$this->baseFields('IPGPaymentToken', $environment),
            'ModalType' => 'IPGPurchase',
            'BannerIndex' => '1',
            'Language' => 'BG',
            'MIDName' => (string) $config['mid_name'],
            'Amount' => number_format((float) $payment->amount, 2, '.', ''),
            'Currency' => (string) $config['currency_numeric'],
            'CustomerIP' => request()->ip() ?? '127.0.0.1',
            // Our own reference, echoed back in the callback's Payment.OrderId
            // — used as providerReference so incoming webhooks can be matched
            // back to this Payment (see parseWebhook() below).
            'OrderID' => $payment->transaction_reference,
            'CustomerIdentifier' => (string) $order->customer_email,
            'Email' => (string) $order->customer_email,
            'URL_Notify' => (string) $config['webhook_url'],
            'Note' => (string) $order->order_number,
            // Mandatory per spec — checkout requires customer.phone, so this
            // is always present (PlaceOrderRequest::rules()).
            'MobileNumber' => $this->normalizePhone((string) $order->customer_phone),
        ];

        // Keep this field set identical to the proven MiswakWebsite request.
        $address = base64_encode(mb_substr((string) $order->shipping_address_line, 0, 50));
        $fields += [
            'BillAddrCountry' => '100',
            'BillAddrCity' => mb_substr((string) $order->shipping_city, 0, 50),
            'BillAddrPostCode' => mb_substr((string) $order->shipping_postal_code, 0, 16),
            'BillAddrLine1' => $address,
            'ShipAddrCountry' => '100',
            'ShipAddrCity' => mb_substr((string) $order->shipping_city, 0, 50),
            'ShipAddrPostCode' => mb_substr((string) $order->shipping_postal_code, 0, 16),
            'ShipAddrLine1' => $address,
        ];

        $response = $this->callIcard($fields, $environment);

        $token = $this->extractField($response, 'Token')
            ?? $this->extractField($response, 'PaymentToken')
            ?? $this->extractField($response, 'PaymentTokenID');

        if ($token === null) {
            throw PaymentGatewayException::missingField('Token');
        }

        return PaymentSessionData::modal(
            providerReference: $payment->transaction_reference,
            token: $token,
            modalJsUrl: (string) $config['modal_js_url'],
            theme: 'dark',
            rawResponse: $response,
        );
    }

    public function createStoredCardSession(Payment $payment, StoredPaymentMethod $storedMethod): PaymentSessionData
    {
        $environment = $payment->gateway_environment ?: $this->environment();
        $config = $this->configuration->assertReady($environment);
        $order = $payment->order;
        $fields = [
            ...$this->baseFields('IPGPaymentToken', $environment),
            'ModalType' => 'IPG3DSPurchaseWithStoredCard',
            'BannerIndex' => '1',
            'Language' => 'BG',
            'CardToken' => $storedMethod->card_token,
            'VerifyCVC' => '1',
            'MIDName' => (string) $config['mid_name'],
            'Amount' => number_format((float) $payment->amount, 2, '.', ''),
            'Currency' => (string) $config['currency_numeric'],
            'OrderID' => $payment->transaction_reference,
            'CustomerIdentifier' => (string) $order->id,
            'Email' => (string) $order->customer_email,
            'MobileNumber' => $this->normalizePhone((string) $order->customer_phone),
            'URL_Notify' => (string) $config['webhook_url'],
            'Note' => (string) $order->order_number,
        ];
        $response = $this->callIcard($fields, $environment);
        $token = $this->extractField($response, 'Token') ?? $this->extractField($response, 'PaymentToken');
        if ($token === null) {
            throw PaymentGatewayException::missingField('Token');
        }
        $storedMethod->update(['last_used_at' => now()]);

        return PaymentSessionData::modal($payment->transaction_reference, $token, (string) $config['modal_js_url'], 'dark', $response);
    }

    public function reverse(Payment $payment): array
    {
        $environment = $payment->gateway_environment ?: $this->environment();
        if (! filled($payment->gateway_transaction_reference)) {
            throw PaymentGatewayException::requestFailed('transaction reference is missing; reversal cannot be submitted');
        }

        return $this->callIcard([
            ...$this->baseFields('IPGReversal', $environment),
            'OrderID' => $this->operationOrderId($payment, 'REV'),
            'IPG_Trnref' => $payment->gateway_transaction_reference,
        ], $environment);
    }

    public function refund(Payment $payment, string $amount): array
    {
        $environment = $payment->gateway_environment ?: $this->environment();
        $config = $this->configuration->assertReady($environment);
        if (! filled($payment->gateway_transaction_reference)) {
            throw PaymentGatewayException::requestFailed('transaction reference is missing; refund cannot be submitted');
        }

        return $this->callIcard([
            ...$this->baseFields('IPGRefund', $environment),
            'OrderID' => $this->operationOrderId($payment, 'RFD'),
            'IPG_Trnref' => $payment->gateway_transaction_reference,
            'Amount' => $amount,
            'Currency' => (string) $config['currency_numeric'],
            'Email' => (string) $payment->order->customer_email,
        ], $environment);
    }

    /**
     * iCard rejects any resubmission of an already-seen (MID, OrderID) pair
     * with "9019 Duplicated transaction" — including on follow-up calls
     * like Reversal/Refund, not just Purchase. $payment->transaction_reference
     * was already submitted once as the OrderID for the original
     * IPGPaymentToken call, so reusing it here would always be rejected;
     * each operation needs its own fresh OrderID. IPG_Trnref (iCard's own
     * reference for the original transaction) is what actually tells iCard
     * which transaction to act on.
     */
    private function operationOrderId(Payment $payment, string $suffix): string
    {
        $safe = preg_replace('/[^A-Za-z0-9-]/', '', (string) $payment->order->order_number) ?: 'SMS';
        $unique = base_convert((string) time(), 10, 36).substr(bin2hex(random_bytes(3)), 0, 6);

        return substr($safe.'-'.$suffix.'-'.$unique, 0, 50);
    }

    /**
     * @return array<string, string>
     */
    private function baseFields(string $ipgMethod, ?string $environment = null): array
    {
        $config = $this->configuration->assertReady($environment);

        return [
            'IPGmethod' => $ipgMethod,
            'KeyIndex' => (string) $config['key_index'],
            'KeyIndexResp' => (string) $config['key_index_resp'],
            'IPGVersion' => (string) $config['ipg_version'],
            'Originator' => (string) $config['originator'],
            'OutputFormat' => 'json',
            'MID' => (string) $config['mid'],
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
    private function callIcard(array $fields, ?string $environment = null): array
    {
        $config = $this->configuration->assertReady($environment);
        $fields['Signature'] = $this->sign($fields, (string) $config['private_key']);
        $body = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);

        Log::info('iCard request', [
            'method' => $fields['IPGmethod'] ?? null,
            'order_id' => $fields['OrderID'] ?? null,
            'environment' => $config['environment'] ?? null,
        ]);

        try {
            $response = Http::withBody($body, 'application/x-www-form-urlencoded')
                ->timeout(35)
                ->post(rtrim((string) $config['base_url'], '/').'/');
        } catch (ConnectionException $exception) {
            throw PaymentGatewayException::requestFailed($exception->getMessage());
        }

        if ($response->failed()) {
            Log::error('iCard HTTP request failed', ['status' => $response->status(), 'method' => $fields['IPGmethod'] ?? null]);
            throw PaymentGatewayException::requestFailed("HTTP {$response->status()}: ".mb_substr(strip_tags($response->body()), 0, 300));
        }

        $parsed = $this->parseIcardResponse($response->body());

        if (! $this->verifyResponseSignature($parsed, (string) $config['public_key'])) {
            throw PaymentGatewayException::requestFailed('response signature verification failed');
        }

        $status = $this->extractField($parsed, 'Status');
        if ($status !== null && $status !== '0') {
            $message = $this->extractField($parsed, 'StatusMsg') ?? $this->extractField($parsed, 'Message') ?? 'Unknown gateway error';
            $errors = $parsed['Errors'] ?? $parsed['errors'] ?? null;
            if (is_array($errors)) {
                $message .= ' '.json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            Log::warning('iCard rejected request', ['method' => $fields['IPGmethod'] ?? null, 'status' => $status, 'message' => $message]);
            throw PaymentGatewayException::requestFailed("{$status} {$message}");
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
     * Protocol 4.5 requires every response to be signed. Unsigned responses
     * are accepted only in the isolated test environment where HTTP fakes
     * deliberately omit signatures.
     *
     * @param  array<int|string, mixed>  $response
     */
    private function verifyResponseSignature(array $response, string $publicKey): bool
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

        return $this->verify($withoutSignature, (string) $signature, $publicKey);
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
        $payload = $this->requestPayload($request);

        $signatureKey = $this->findKey($payload, 'Signature');
        if ($signatureKey === null || ! is_string($payload[$signatureKey])) {
            return false;
        }

        $signature = $payload[$signatureKey];
        unset($payload[$signatureKey]);

        $reference = $this->nestedValue($payload, ['Payment', 'OrderId']);
        $payment = is_string($reference) ? Payment::query()->where('provider_reference', $reference)->first() : null;
        $config = $this->configuration->active($payment?->gateway_environment);

        $allowedIps = $config['callback_ips'] ?? [];
        if (is_array($allowedIps) && $allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true)) {
            return false;
        }

        return filled($config['public_key'] ?? null) && $this->verify($payload, $signature, (string) $config['public_key']);
    }

    public function parseWebhook(Request $request): WebhookPayloadData
    {
        $payload = $this->requestPayload($request);
        $payment = $this->nestedValue($payload, ['Payment']);
        $operation = $this->nestedValue($payload, ['Operation']);

        $orderId = is_array($payment) ? $this->nestedValue($payment, ['OrderId']) : null;
        $paymentStatus = is_array($payment) ? $this->nestedValue($payment, ['Status']) : null;
        if (! is_array($payment) || ! is_scalar($orderId) || ! is_scalar($paymentStatus)) {
            throw InvalidWebhookPayloadException::missingFields();
        }

        $sumValue = $this->nestedValue($payment, ['Sum']);
        $sum = is_array($sumValue) ? $sumValue : null;
        $operation = is_array($operation) ? $operation : null;
        $amount = $sum !== null ? $this->nestedValue($sum, ['Amount']) : null;
        $currency = $sum !== null ? $this->nestedValue($sum, ['Currency']) : null;

        return new WebhookPayloadData(
            eventType: (string) ($this->nestedValue($operation ?? [], ['Type']) ?? $paymentStatus),
            providerReference: (string) $orderId,
            status: $this->mapCallbackToStatus((string) $paymentStatus, $operation),
            amount: is_scalar($amount) ? (float) $amount : null,
            currency: is_scalar($currency) ? (string) $currency : null,
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
        $paymentStatus = mb_strtolower($paymentStatus);
        if ($paymentStatus === 'declined' || $paymentStatus === 'error') {
            return PaymentStatus::Failed;
        }

        $operationStatus = mb_strtolower((string) ($this->nestedValue($operation ?? [], ['Status']) ?? ''));

        if ($paymentStatus === 'success' && $operationStatus === 'success') {
            return PaymentStatus::Paid;
        }

        return PaymentStatus::Authorized;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', trim($phone)) ?? '';
        if (str_starts_with($phone, '0')) {
            $phone = '+359'.substr($phone, 1);
        }
        if (! str_starts_with($phone, '+') && $phone !== '') {
            $phone = '+'.$phone;
        }

        return $phone;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sign(array $data, string $privateKeyPem): string
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);

        if ($privateKey === false) {
            throw PaymentGatewayException::requestFailed('invalid private key: '.openssl_error_string());
        }

        $dataToSign = $this->canonicalize($data);
        if (! openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw PaymentGatewayException::requestFailed('could not sign request');
        }

        return base64_encode($signature);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function verify(array $data, string $signatureBase64, string $publicKeyPem): bool
    {
        $publicKey = openssl_pkey_get_public($publicKeyPem);

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

            if ($keySegment === 'signature') {
                continue;
            }

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

    /** @return array<string, mixed> */
    private function requestPayload(Request $request): array
    {
        $payload = $request->json()->all();
        if ($payload !== []) {
            return $payload;
        }

        $payload = $request->all();
        if ($payload !== []) {
            return $payload;
        }

        $raw = $request->getContent();
        parse_str($raw, $parsed);

        return $parsed;
    }

    /** @param array<int|string, mixed> $data */
    private function findKey(array $data, string $wanted): int|string|null
    {
        foreach ($data as $key => $_value) {
            if (strcasecmp((string) $key, $wanted) === 0) {
                return $key;
            }
        }

        return null;
    }

    /** @param array<int|string, mixed> $data @param list<string> $path */
    private function nestedValue(array $data, array $path): mixed
    {
        $value = $data;
        foreach ($path as $segment) {
            if (! is_array($value)) {
                return null;
            }
            $key = $this->findKey($value, $segment);
            if ($key === null) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }
}
