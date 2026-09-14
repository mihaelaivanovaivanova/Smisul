<?php

namespace App\Enums;

/**
 * iCard handles every card payment — the PaymentGatewayInterface contract
 * it implements is what a second real gateway would hang off later: a new
 * case here, a new class implementing the interface, one line in
 * PaymentService's provider resolution. CashOnDelivery is the other real,
 * live case: no gateway is involved at all (see PaymentService::initiate()'s
 * own branch), it exists purely to record who/what a Payment row is
 * against — same Payment/PaymentTransaction schema either way.
 */
enum PaymentProvider: string
{
    case ICard = 'icard';
    case CashOnDelivery = 'cash_on_delivery';

    public function label(): string
    {
        return match ($this) {
            self::ICard => 'iCard',
            self::CashOnDelivery => 'Наложен платеж',
        };
    }
}
