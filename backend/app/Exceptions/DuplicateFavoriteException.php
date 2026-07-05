<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DuplicateFavoriteException extends Exception
{
    public static function create(): self
    {
        return new self('This product is already in your favorites.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
