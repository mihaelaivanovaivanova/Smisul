<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsufficientStockException extends Exception
{
    public static function forVariant(string $sku, int $requested, int $available): self
    {
        return new self(
            "Cannot fulfill request for {$requested} unit(s) of SKU [{$sku}] — only {$available} available."
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
