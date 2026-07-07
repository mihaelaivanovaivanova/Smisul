<?php

namespace App\Exceptions\Payment;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thrown when a call to iCard's IPG API itself fails outright (connection
 * error, non-2xx response, or a 2xx response missing the field the caller
 * actually needed — e.g. no Token from IPGPaymentToken). Distinct from
 * InvalidWebhookSignatureException/InvalidWebhookPayloadException, which
 * are about iCard calling us, not us calling iCard.
 */
class PaymentGatewayException extends Exception
{
    public static function requestFailed(string $reason): self
    {
        return new self("iCard API request failed: {$reason}");
    }

    public static function missingField(string $field): self
    {
        return new self("iCard API response did not include the expected field: {$field}");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 502);
    }
}
