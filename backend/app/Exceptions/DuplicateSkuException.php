<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DuplicateSkuException extends Exception
{
    public static function forSku(string $sku): self
    {
        return new self("SKU [{$sku}] is already in use by another variant.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
