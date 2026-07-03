<?php

namespace App\Exceptions\Payment;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidWebhookSignatureException extends Exception
{
    public static function create(): self
    {
        return new self('Webhook signature verification failed.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 401);
    }
}
