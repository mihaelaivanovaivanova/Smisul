<?php

namespace App\Enums;

/**
 * The instrument the customer pays with — orthogonal to PaymentProvider
 * (which gateway integration handles the charge). All three methods here
 * go through the same iCard IPGPurchase hosted-redirect flow today; adding
 * a genuinely different provider for one of them later would still just be
 * a new PaymentGatewayInterface binding, not a change to this enum.
 */
enum PaymentMethod: string
{
    case Card = 'card';
    case ApplePay = 'apple_pay';
    case GooglePay = 'google_pay';

    public function label(): string
    {
        return match ($this) {
            self::Card => 'Карта',
            self::ApplePay => 'Apple Pay',
            self::GooglePay => 'Google Pay',
        };
    }
}
