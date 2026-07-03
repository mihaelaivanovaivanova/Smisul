<?php

namespace App\DataTransferObjects\Cart;

final readonly class UpdateCartItemData
{
    public function __construct(
        public int $quantity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            quantity: (int) $data['quantity'],
        );
    }
}
