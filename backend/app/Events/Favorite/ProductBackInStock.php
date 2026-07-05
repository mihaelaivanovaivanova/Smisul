<?php

namespace App\Events\Favorite;

use App\Models\ProductVariant;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched by InventoryService whenever a variant transitions from
 * out-of-stock to in-stock. NotifyFavoritesOfBackInStock listens and
 * emails everyone who has this variant favorited.
 */
class ProductBackInStock
{
    use Dispatchable;

    public function __construct(
        public readonly ProductVariant $variant,
    ) {}
}
