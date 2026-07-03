<?php

namespace App\Exceptions\Cart;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartItemNotFoundException extends Exception
{
    public static function forId(int $id): self
    {
        return new self("No cart item found for id [{$id}].");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 404);
    }
}
