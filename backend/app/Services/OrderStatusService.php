<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\Order\OrderStatusChanged;
use App\Exceptions\Order\InvalidOrderStatusTransitionException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single source of truth for the order status workflow: which
 * transitions are allowed, and recording every change as an immutable
 * OrderStatusHistory row. OrderService calls transitionTo() for the moves
 * it already knows how to perform (confirmPayment, cancel); the admin
 * status-update endpoint (Api\V1\Admin\OrderController) calls it for
 * everything else. No other code should ever write Order::status directly.
 */
class OrderStatusService
{
    /**
     * Adjacency list of allowed forward moves. Extensible by design: adding
     * a new status/edge means editing this map only — every caller
     * (OrderService, the admin API, tests) already goes through
     * transitionTo()/allowedTransitions(), so nothing else needs to change.
     *
     * @var array<string, list<OrderStatus>>
     */
    private const TRANSITIONS = [
        OrderStatus::Pending->value => [OrderStatus::AwaitingPayment, OrderStatus::Paid, OrderStatus::Cancelled, OrderStatus::Failed],
        OrderStatus::AwaitingPayment->value => [OrderStatus::Paid, OrderStatus::Failed, OrderStatus::Cancelled],
        OrderStatus::Paid->value => [OrderStatus::Processing, OrderStatus::Refunded, OrderStatus::Cancelled],
        OrderStatus::Processing->value => [OrderStatus::Packed, OrderStatus::Cancelled],
        OrderStatus::Packed->value => [OrderStatus::Shipped, OrderStatus::Cancelled],
        OrderStatus::Shipped->value => [OrderStatus::Delivered],
        OrderStatus::Delivered->value => [OrderStatus::Completed, OrderStatus::Refunded],
        OrderStatus::Completed->value => [OrderStatus::Refunded],
        OrderStatus::Cancelled->value => [OrderStatus::Refunded],
        OrderStatus::Refunded->value => [],
        OrderStatus::Failed->value => [OrderStatus::Pending, OrderStatus::Cancelled],
    ];

    /**
     * @return list<OrderStatus>
     */
    public function allowedTransitions(OrderStatus $from): array
    {
        return self::TRANSITIONS[$from->value];
    }

    /**
     * The very first history entry for a newly-placed order — always
     * Pending, with no previous status and no acting user (the customer
     * placing an order isn't "changing its status", the system is setting
     * its initial one).
     */
    public function recordInitial(Order $order): void
    {
        $order->statusHistories()->create([
            'status' => OrderStatus::Pending,
            'previous_status' => null,
            'changed_by_user_id' => null,
            'note' => null,
        ]);
    }

    public function transitionTo(Order $order, OrderStatus $to, ?User $changedBy, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $to, $changedBy, $note) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first() ?? $order;
            $from = $order->status;

            if (! in_array($to, $this->allowedTransitions($from), strict: true)) {
                throw InvalidOrderStatusTransitionException::notAllowed($order->order_number, $from, $to);
            }

            $order->statusHistories()->create([
                'status' => $to,
                'previous_status' => $from,
                'changed_by_user_id' => $changedBy?->id,
                'note' => $note,
            ]);

            $order->update(['status' => $to]);

            OrderStatusChanged::dispatch($order, $from, $to);

            return $order;
        });
    }
}
