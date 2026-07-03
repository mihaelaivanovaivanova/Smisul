<?php

namespace App\Enums;

enum Role: string
{
    case Customer = 'customer';
    case Administrator = 'administrator';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Administrator => 'Administrator',
        };
    }
}
