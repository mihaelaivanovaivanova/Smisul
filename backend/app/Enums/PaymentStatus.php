<?php

namespace App\Enums;

/**
 * A payment's own lifecycle — separate from OrderStatus (see
 * PaymentService), since one order could in principle have more than one
 * payment attempt (e.g. retry after Failed). Pending: created, no gateway
 * interaction yet. Initiated: redirect URL handed to the customer.
 * Authorized: gateway approved the charge but hasn't finally settled it
 * (kept distinct from Paid for gateways that separate authorize/capture —
 * iCard's hosted flow may report this before final confirmation).
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Initiated = 'initiated';
    case Authorized = 'authorized';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Initiated => 'Initiated',
            self::Authorized => 'Authorized',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
            self::Refunded => 'Refunded',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Failed, self::Cancelled, self::Expired, self::Refunded], strict: true);
    }
}
