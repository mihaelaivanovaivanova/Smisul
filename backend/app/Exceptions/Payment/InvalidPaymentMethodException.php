<?php

namespace App\Exceptions\Payment;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thrown by PaymentService as a defense-in-depth check — the FormRequest
 * layer (see PlaceOrderRequest/InitiatePaymentRequest) already rejects a
 * disabled/unknown method with a 422 before this is ever reached in
 * normal use, but PaymentService::initiate() is also called from
 * anywhere else that constructs a PaymentMethod directly (tests, future
 * callers), so it can't assume that validation always ran first.
 */
class InvalidPaymentMethodException extends Exception
{
    public static function disabled(string $method): self
    {
        return new self("The \"{$method}\" payment method is not currently enabled.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
