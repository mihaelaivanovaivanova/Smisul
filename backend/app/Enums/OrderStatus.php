<?php

namespace App\Enums;

/**
 * The full order lifecycle (see OrderStatusService::TRANSITIONS for which
 * moves between these are actually allowed). Every order is created as
 * Pending. Payment isn't integrated yet (Sprint 5/6 scope) — AwaitingPayment
 * and Paid exist so the workflow is ready for it, but nothing transitions
 * an order into them automatically today; OrderService::confirmPayment()
 * is the seam a future payment gateway callback will call.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::AwaitingPayment => 'Awaiting Payment',
            self::Paid => 'Paid',
            self::Processing => 'Processing',
            self::Packed => 'Packed',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
            self::Failed => 'Failed',
        };
    }
}
