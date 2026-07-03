<?php

namespace App\Exceptions\Checkout;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmptyCartException extends Exception
{
    public static function create(): self
    {
        return new self('Your cart is empty — add something before checking out.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
