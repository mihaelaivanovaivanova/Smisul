<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Format: SM-YYMMDD-XXXXXX (e.g. SM-260703-A1B2C3) — human-scannable date
 * prefix plus a random suffix. Collisions are astronomically unlikely at
 * this store's scale, but the existence check + retry loop makes the
 * uniqueness guarantee absolute rather than probabilistic.
 */
class OrderNumberGenerator
{
    public function generate(): string
    {
        do {
            $candidate = 'SM-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Order::where('order_number', $candidate)->exists());

        return $candidate;
    }
}
