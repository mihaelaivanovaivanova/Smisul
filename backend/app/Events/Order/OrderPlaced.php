<?php

namespace App\Events\Order;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched once an order is fully persisted (items, legal acceptances,
 * inventory already committed). No listener today - the customer
 * confirmation + admin notification emails that used to fire here now wait
 * for OrderStatusChanged's Paid transition instead (see
 * SendOrderStatusEmails's docblock: a card payment can still fail or be
 * abandoned after placement, so "order" emails shouldn't go out before the
 * order is actually paid for). Kept as an event rather than removed
 * entirely so a future listener (analytics, abandoned-order follow-up) can
 * be added without touching order placement itself.
 */
class OrderPlaced implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
    ) {}
}
