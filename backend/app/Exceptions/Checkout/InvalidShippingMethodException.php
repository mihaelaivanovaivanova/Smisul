<?php

namespace App\Exceptions\Checkout;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidShippingMethodException extends Exception
{
    public static function forCarrier(string $carrier): self
    {
        return new self("'{$carrier}' is not a valid shipping method.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
