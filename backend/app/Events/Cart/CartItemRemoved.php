<?php

namespace App\Events\Cart;

use App\Models\Cart;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Exactly one of $productVariantId / $bundleId is set - the same "either an
 * ordinary product line or a kit line" invariant CartItem enforces.
 */
class CartItemRemoved
{
    use Dispatchable;

    public function __construct(
        public readonly Cart $cart,
        public readonly ?int $productVariantId,
        public readonly ?int $bundleId = null,
    ) {}
}
