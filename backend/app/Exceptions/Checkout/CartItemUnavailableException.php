<?php

namespace App\Exceptions\Checkout;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thrown when the final re-validation pass at order placement finds a cart
 * line that's no longer purchasable (product unpublished, variant
 * deactivated, or stock no longer covers the requested quantity) — always
 * possible since time passes between "add to cart" and "place order".
 * Carries every affected SKU so the frontend can point the customer back
 * at their cart with a specific, actionable message instead of a generic
 * failure.
 */
class CartItemUnavailableException extends Exception
{
    /**
     * @param  list<string>  $skus
     */
    public function __construct(private readonly array $skus)
    {
        parent::__construct('Some items in your cart are no longer available: '.implode(', ', $skus));
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'skus' => $this->skus,
        ], 422);
    }
}
