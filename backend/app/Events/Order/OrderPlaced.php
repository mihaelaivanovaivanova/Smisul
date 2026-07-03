<?php

namespace App\Events\Order;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched once an order is fully persisted (items, legal acceptances,
 * inventory already committed). SendOrderPlacedNotifications is the only
 * listener today (customer confirmation + admin notification); kept as an
 * event rather than inline calls in OrderService so future listeners
 * (analytics, fulfillment webhooks) can be added without touching order
 * placement itself.
 */
class OrderPlaced
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
    ) {}
}
