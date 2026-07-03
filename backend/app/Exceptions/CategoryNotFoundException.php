<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryNotFoundException extends Exception
{
    public static function forSlug(string $slug): self
    {
        return new self("No category found for slug [{$slug}].");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 404);
    }
}
