<?php

namespace App\Events\Cart;

use App\Models\Cart;
use Illuminate\Foundation\Events\Dispatchable;

class CartItemRemoved
{
    use Dispatchable;

    public function __construct(
        public readonly Cart $cart,
        public readonly int $productVariantId,
    ) {}
}
