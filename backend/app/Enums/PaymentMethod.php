<?php

namespace App\Enums;

/**
 * The instrument the customer pays with — orthogonal to PaymentProvider
 * (which gateway integration handles the charge). All methods here go
 * through the same iCard IPGPurchase hosted-redirect flow today; adding a
 * genuinely different provider for one of them later would still just be
 * a new PaymentGatewayInterface binding, not a change to this enum.
 *
 * CashOnDelivery was removed from checkout entirely (BOX NOW deliveries
 * are card-only now — no courier ever collected cash in person, and BOX
 * NOW's own payment portal at locker pickup is being dropped too), but the
 * case stays here on purpose, same as ShippingCarrier::Econt: existing
 * `payments` rows placed while it was still offered still store
 * `payment_method = 'cash_on_delivery'`, and Eloquent's native enum cast
 * throws (not silently nulls) when a persisted value has no matching case.
 * Removing it outright 500s every read of that historical data. Use
 * active() — never cases() — anywhere the question is "which methods can
 * a customer pick today", so it can't reappear as a live checkout option
 * while old orders still display correctly.
 */
enum PaymentMethod: string
{
    case Card = 'card';

    /** Historical values only; wallets now appear inside the iCard modal. */
    case ApplePay = 'apple_pay';
    case GooglePay = 'google_pay';

    /** Historical value only — see this enum's own doc comment. */
    case CashOnDelivery = 'cash_on_delivery';

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
}
