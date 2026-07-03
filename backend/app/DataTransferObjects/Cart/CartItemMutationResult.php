<?php

namespace App\DataTransferObjects\Cart;

use App\Models\CartItem;

final readonly class CartItemMutationResult
{
    public function __construct(
        public CartItem $item,
        public bool $wasCreated,
    ) {}
}
