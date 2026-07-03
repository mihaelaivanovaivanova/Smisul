<?php

namespace App\Enums;

/**
 * Only BGN is used today. The schema (a "currency" column per price row)
 * already supports adding more currencies later without a migration —
 * this enum is deliberately small until a second currency is truly needed.
 */
enum Currency: string
{
    case BGN = 'BGN';

    public function symbol(): string
    {
        return match ($this) {
            self::BGN => 'лв.',
        };
    }
}
