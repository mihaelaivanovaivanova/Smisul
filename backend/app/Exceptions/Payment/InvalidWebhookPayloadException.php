<?php

namespace App\Exceptions\Payment;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidWebhookPayloadException extends Exception
{
    public static function missingFields(): self
    {
        return new self('Webhook payload is missing required fields.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
