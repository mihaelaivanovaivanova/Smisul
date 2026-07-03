<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Guest orders (user_id null) aren't covered here — access to those is by
 * guest_access_token instead, checked directly in OrderController::show()
 * since it isn't a "does this authenticated user own it" question.
 */
class OrderPolicy
{
    public function view(User $authUser, Order $order): bool
    {
        return $order->user_id !== null && $authUser->is($order->user);
    }
}
