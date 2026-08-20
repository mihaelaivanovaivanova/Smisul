<?php

namespace App\Enums;

/**
 * The carriers checkout can offer today as generic, flat-rate options (see
 * ShippingMethodService). Adding real rate quoting or label generation
 * later means teaching ShippingMethodService to call out per-carrier; this
 * enum (and the carrier column it backs on `orders`) doesn't need to change.
 *
 * Econt was removed from checkout entirely, but the case stays here
 * on purpose: existing `orders`/`shipments` rows placed while it was still
 * offered still store `carrier = 'econt'`, and Eloquent's native enum cast
 * throws (not silently nulls) when a persisted value has no matching case.
 * Removing it outright 500s every read of that historical data. Use
 * active() — never cases() — anywhere the question is "which carriers can
 * a customer pick today", so Econt can't reappear as a live checkout
 * option while old orders still display correctly.
 */
enum ShippingCarrier: string
{
    case Speedy = 'speedy';
    case BoxNow = 'box_now';
    case Econt = 'econt';

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Speedy, self::BoxNow];
    }

    public function label(): string
    {
        return match ($this) {
            self::Speedy => 'Speedy',
            self::BoxNow => 'BOX NOW',
            self::Econt => 'Econt',
        };
    }
}
