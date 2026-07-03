<?php

namespace App\DataTransferObjects\Cart;

/**
 * Discount/shipping/tax are explicit zero-valued placeholders rather than
 * omitted fields, so the response shape is already stable for when those
 * features (discount codes, shipping calculation, VAT) land later.
 */
final readonly class CartTotals
{
    public function __construct(
        public float $subtotal,
        public float $discountTotal,
        public float $shippingTotal,
        public float $taxTotal,
        public float $grandTotal,
        public string $currency,
    ) {}
}
