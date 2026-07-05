<?php

namespace App\Events\Favorite;

use App\Models\ProductVariant;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched by PriceService::setPrice() whenever a variant's price for a
 * currency decreases. NotifyFavoritesOfPriceDrop listens and emails
 * everyone who has this variant favorited.
 */
class ProductPriceDropped
{
    use Dispatchable;

    public function __construct(
        public readonly ProductVariant $variant,
        public readonly float $oldAmount,
        public readonly float $newAmount,
        public readonly string $currency,
    ) {}
}
