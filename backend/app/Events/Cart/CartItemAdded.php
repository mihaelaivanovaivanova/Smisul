<?php

namespace App\Events\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched for future extensibility (analytics, abandoned-cart
 * reminders, recommendation engines) — no listeners are registered yet
 * since nothing meaningful consumes this today.
 */
class CartItemAdded
{
    use Dispatchable;

    public function __construct(
        public readonly Cart $cart,
        public readonly CartItem $cartItem,
    ) {}
}
