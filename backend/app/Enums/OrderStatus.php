<?php

namespace App\Enums;

/**
 * Payment isn't integrated yet (see Sprint 5 scope) — every order is
 * created as Pending. The payment sprint will add the transition into
 * Processing (payment captured) and Cancelled (payment failed/expired);
 * Completed is reserved for fulfillment, handled outside this sprint.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }
}
