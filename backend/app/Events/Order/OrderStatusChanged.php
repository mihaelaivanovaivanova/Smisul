<?php

namespace App\Events\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched by OrderStatusService::transitionTo() after every status
 * change is committed. SendOrderStatusEmails listens for the subset of
 * transitions that warrant a customer email (Paid, Cancelled, Shipped,
 * Delivered) — see that listener for the exact mapping.
 */
class OrderStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
    ) {}
}
