<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewNotEligibleException extends Exception
{
    public static function notPurchased(): self
    {
        return new self('You can only review a product you have purchased.');
    }

    public static function notDelivered(): self
    {
        return new self('You can only review products from an order that has been delivered.');
    }

    public static function alreadyReviewed(): self
    {
        return new self('You have already submitted a review for this product from this order.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
