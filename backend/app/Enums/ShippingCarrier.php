<?php

namespace App\Enums;

/**
 * The carriers checkout can offer today as generic, flat-rate options (see
 * ShippingMethodService) — no carrier API is integrated yet. Adding real
 * Speedy/BOX NOW rate quoting or label generation later means teaching
 * ShippingMethodService to call out per-carrier; this enum (and the carrier
 * column it backs on `orders`) doesn't need to change.
 */
enum ShippingCarrier: string
{
    case Speedy = 'speedy';
    case BoxNow = 'box_now';

    public function label(): string
    {
        return match ($this) {
            self::Speedy => 'Speedy',
            self::BoxNow => 'BOX NOW',
        };
    }
}
