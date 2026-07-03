<?php

namespace App\Exceptions\Cart;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VariantNotPurchasableException extends Exception
{
    public static function forSku(string $sku): self
    {
        return new self("SKU [{$sku}] is not currently available for purchase.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
