<?php

namespace App\Enums;

/**
 * iCard is the only implemented provider this sprint. Apple Pay/Google Pay/
 * cash-on-delivery are explicitly out of scope (see the sprint brief) but
 * this enum — and the PaymentGatewayInterface contract every provider
 * implements — is what adding them later hangs off: a new case here, a new
 * class implementing the interface, one line in PaymentService's provider
 * resolution. Nothing about the Payment/PaymentTransaction schema changes.
 */
enum PaymentProvider: string
{
    case ICard = 'icard';

    public function label(): string
    {
        return match ($this) {
            self::ICard => 'iCard',
        };
    }
}
