<?php

namespace Tests\Feature\Payments\Concerns;

/**
 * Signs a callback payload exactly the way iCard's IPG API does (see
 * ICardPaymentGateway::sign()/canonicalize()) using the test-only key pair
 * configured in phpunit.xml. Since ICardPaymentGateway::verifySignature()
 * checks against the matching test public key, this stands in for "iCard"
 * when a test needs to deliver a webhook that passes verification.
 */
trait SignsICardCallbacks
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function signICardPayload(array $payload): array
    {
        $lines = $this->flattenForSigning($payload);
        sort($lines, SORT_NATURAL);
        $dataToSign = implode(';', $lines);

        $privateKey = openssl_pkey_get_private((string) file_get_contents(config('services.icard.private_key_path')));
        openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $payload + ['Signature' => base64_encode($signature)];
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @param  array<int, string>  $parents
     * @return array<int, string>
     */
    private function flattenForSigning(array $data, array $parents = []): array
    {
        $lines = [];

        foreach ($data as $key => $value) {
            $keySegment = is_int($key) ? (string) $key : mb_strtolower((string) $key);

            if (is_array($value)) {
                if ($value === []) {
                    continue;
                }

                array_push($lines, ...$this->flattenForSigning($value, [...$parents, $keySegment]));

                continue;
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            $lines[] = implode(':', [...$parents, $keySegment, (string) $value]);
        }

        return $lines;
    }
}
