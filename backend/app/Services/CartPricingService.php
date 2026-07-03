<?php

namespace App\Services;

use App\DataTransferObjects\Cart\CartTotals;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Price;
use App\Models\ProductVariant;

/**
 * All cart pricing/availability calculations live here, isolated from
 * cart mutation orchestration (CartService), so that future discount
 * codes, shipping calculation, and tax rules have a single, obvious
 * extension point without touching add/update/remove logic.
 */
class CartPricingService
{
    /**
     * Absolute ceiling per line item, independent of stock — a defensive
     * cap so a single line can never carry an unreasonable quantity even
     * when stock is effectively unlimited (backorders_allowed).
     */
    public const MAX_QUANTITY_PER_ITEM = 99;

    public function isVariantPurchasable(ProductVariant $variant): bool
    {
        return $variant->product !== null && $variant->product->isPublished() && $variant->isActive();
    }

    /**
     * The highest quantity a cart line for this variant could reach,
     * respecting both the absolute per-item cap and available stock
     * (unless backorders are allowed). $alreadyHeldByThisLine is the
     * quantity an existing cart_item for this variant already reserved —
     * that stock is "spent" from the shared availableQuantity() pool, so
     * it has to be added back to find this line's own ceiling. Pass 0 (the
     * default) when there's no existing line yet (e.g. a fresh add-to-cart).
     */
    public function maxQuantityFor(ProductVariant $variant, int $alreadyHeldByThisLine = 0): int
    {
        $inventory = $variant->inventory;

        if ($inventory === null) {
            return $alreadyHeldByThisLine;
        }

        if ($inventory->backorders_allowed) {
            return self::MAX_QUANTITY_PER_ITEM;
        }

        return max(
            $alreadyHeldByThisLine,
            min(self::MAX_QUANTITY_PER_ITEM, $inventory->availableQuantity() + $alreadyHeldByThisLine),
        );
    }

    /**
     * Whether a cart line is still fully purchasable — false if the
     * product/variant became unpurchasable, or if the variant's reserved
     * stock now exceeds what's physically on hand (e.g. an admin corrected
     * the stock count after this item was already reserved). Because
     * adding/updating a cart item reserves stock at write time (see
     * CartService + InventoryService::reserve), a line's own quantity can
     * no longer outgrow stock on its own — this is only ever tripped by an
     * external change to the shared inventory row.
     */
    public function isItemAvailable(CartItem $item): bool
    {
        $variant = $item->productVariant;

        if ($variant === null || ! $this->isVariantPurchasable($variant)) {
            return false;
        }

        $inventory = $variant->inventory;

        if ($inventory === null) {
            return false;
        }

        return $inventory->backorders_allowed || $inventory->quantity_on_hand >= $inventory->quantity_reserved;
    }

    public function unitPrice(ProductVariant $variant, string $currency): ?Price
    {
        return $variant->priceFor($currency);
    }

    public function lineTotal(CartItem $item, string $currency): float
    {
        $variant = $item->productVariant;
        $price = $variant ? $this->unitPrice($variant, $currency) : null;

        return $price ? round((float) $price->amount * $item->quantity, 2) : 0.0;
    }

    /**
     * Aggregate totals for the cart. Discount/shipping/tax are explicit
     * zero-valued placeholders (see CartTotals) since none of those are
     * implemented yet — the shape is stable for when they land.
     */
    public function totals(Cart $cart): CartTotals
    {
        $currency = $cart->currency;

        $subtotal = round(
            $cart->items->sum(fn (CartItem $item) => $this->lineTotal($item, $currency)),
            2,
        );

        $discountTotal = 0.0;
        $shippingTotal = 0.0;
        $taxTotal = 0.0;

        return new CartTotals(
            subtotal: $subtotal,
            discountTotal: $discountTotal,
            shippingTotal: $shippingTotal,
            taxTotal: $taxTotal,
            grandTotal: round($subtotal - $discountTotal + $shippingTotal + $taxTotal, 2),
            currency: $currency,
        );
    }
}
