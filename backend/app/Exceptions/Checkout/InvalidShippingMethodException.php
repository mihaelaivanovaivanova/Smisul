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

    public static function forCarrierAndDeliveryType(string $carrier, string $deliveryType): self
    {
        return new self("'{$carrier}' does not offer delivery type '{$deliveryType}'.");
    }

    public static function officeRequired(string $carrier, string $deliveryType): self
    {
        return new self("An office/locker must be selected for '{$carrier}' with delivery type '{$deliveryType}'.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
