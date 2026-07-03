<?php

namespace App\Enums;

enum PromotionType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage',
            self::FixedAmount => 'Fixed amount',
        };
    }
}
