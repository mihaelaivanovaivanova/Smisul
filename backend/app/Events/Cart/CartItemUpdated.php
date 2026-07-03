<?php

namespace App\Events\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Foundation\Events\Dispatchable;

class CartItemUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly Cart $cart,
        public readonly CartItem $cartItem,
    ) {}
}
