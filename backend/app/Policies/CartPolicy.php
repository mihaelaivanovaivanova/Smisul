<?php

namespace App\Policies;

use App\Models\Cart;
use App\Models\User;

/**
 * Guests can't hold a Policy subject (there's no authenticated User to
 * check against), so this only covers the authenticated-owner case. The
 * actual security boundary for guest carts — and the primary boundary
 * even for authenticated ones — is that every cart_item lookup is scoped
 * through the resolved Cart's own relation query (see CartService), never
 * a bare CartItem::find(). This policy is defense-in-depth for any future
 * code path (e.g. an admin view) that reaches a Cart via a User directly.
 */
class CartPolicy
{
    public function view(User $user, Cart $cart): bool
    {
        return $cart->user_id === $user->id;
    }

    public function update(User $user, Cart $cart): bool
    {
        return $cart->user_id === $user->id;
    }
}
