<?php

namespace App\Events\Cart;

use App\Models\Cart;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a guest cart has been merged into an authenticated
 * user's cart and the guest cart row deleted.
 */
class CartMerged
{
    use Dispatchable;

    public function __construct(
        public readonly Cart $userCart,
        public readonly int $mergedItemCount,
    ) {}
}
