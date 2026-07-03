<?php

namespace App\DataTransferObjects\Cart;

final readonly class AddCartItemData
{
    public function __construct(
        public int $productVariantId,
        public int $quantity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            productVariantId: (int) $data['product_variant_id'],
            quantity: (int) $data['quantity'],
        );
    }
}
