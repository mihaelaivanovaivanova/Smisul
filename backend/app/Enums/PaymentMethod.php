<?php

namespace App\Enums;

/**
 * The instrument the customer pays with — orthogonal to PaymentProvider
 * (which gateway integration handles the charge). Card goes through the
 * iCard IPGPurchase hosted-redirect flow; CashOnDelivery bypasses a
 * gateway entirely (see PaymentService::initiate()'s own branch).
 *
 * CashOnDelivery is live again, but only for Speedy orders — Speedy's
 * courier physically collects cash (or a card payment, unless
 * cardPaymentForbidden) at hand-off, which BOX NOW's locker network has no
 * equivalent for (no courier ever meets the customer in person there).
 * This case was removed from checkout entirely once before (BOX NOW used
 * to offer a different COD mechanic — its own in-app payment portal at
 * locker pickup, not cash to a person) and re-added for Speedy instead;
 * see PaymentService::availablePaymentMethods() for the actual carrier
 * gate. active() only lists the methods with no such gate — use
 * PaymentService::availablePaymentMethods($carrier), not this method
 * directly, anywhere the real question is "what can this order's carrier
 * be paid with".
 */
enum PaymentMethod: string
{
    case Card = 'card';
    case CashOnDelivery = 'cash_on_delivery';

    /** Historical values only; wallets now appear inside the iCard modal. */
    case ApplePay = 'apple_pay';
    case GooglePay = 'google_pay';

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Card];
    }

    public function label(): string
    {
        return match ($this) {
            self::Card => 'Плащане с карта',
            self::ApplePay => 'Apple Pay',
            self::GooglePay => 'Google Pay',
            self::CashOnDelivery => 'Наложен платеж',
        };
    }

    /**
     * The surcharge for choosing this method, added to an order's
     * grand_total the moment it's actually selected (see
     * PaymentService::initiate()) — zero for every method except cash on
     * delivery.
     */
    public function fee(): float
    {
        return match ($this) {
            self::CashOnDelivery => (float) config('services.payments.cash_on_delivery_fee', 0.50),
            default => 0.0,
        };
    }
}
