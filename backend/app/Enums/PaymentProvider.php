<?php

namespace App\Enums;

/**
 * iCard is the only active provider — the PaymentGatewayInterface contract
 * it implements is what a second real provider would hang off later: a new
 * case here, a new class implementing the interface, one line in
 * PaymentService's provider resolution. Nothing about the Payment/
 * PaymentTransaction schema changes.
 *
 * CashOnDelivery is a historical value only (see PaymentMethod's own doc
 * comment for the full removal rationale — same ShippingCarrier::Econt
 * pattern): existing `payments` rows still store `provider =
 * 'cash_on_delivery'`, so the case stays for Eloquent's enum cast to keep
 * reading them correctly, but nothing creates a new one anymore.
 */
enum PaymentProvider: string
{
    case ICard = 'icard';

    /** Historical value only — see this enum's own doc comment. */
    case CashOnDelivery = 'cash_on_delivery';

    public function label(): string
    {
        return match ($this) {
            self::ICard => 'iCard',
            self::CashOnDelivery => 'Наложен платеж',
        };
    }
}
