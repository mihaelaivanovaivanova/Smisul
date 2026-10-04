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

    /**
     * The struck-through "was" price for a multi-piece pack variant that
     * isn't otherwise on sale — what this many pieces would cost at the
     * product's own live 1-piece price, the same non-fabricated "vs. buying
     * singly" methodology the frontend's funnelOffers.ts::computeOriginalPrice
     * uses for the funnel page's package cards. Never a stored/fabricated
     * compare-at value (see FunnelSeeder.php's history: compare_at_amount
     * was deliberately removed from pack-variant Price rows for having no
     * documented basis) — this is computed fresh from a real sibling price
     * every time. Null whenever this isn't a multi-piece pack, the product
     * has no 1-piece sibling variant, or that sibling has no price in this
     * currency.
     */
    public function bundleCompareAtUnitPrice(ProductVariant $variant, string $currency): ?float
    {
        if ($variant->pack_size <= 1 || $variant->product === null) {
            return null;
        }

        $singleVariant = $variant->product->variants->first(
            fn (ProductVariant $candidate) => $candidate->pack_size === 1,
        );
        $singlePrice = $singleVariant?->priceFor($currency);

        return $singlePrice ? round((float) $singlePrice->amount * $variant->pack_size, 2) : null;
    }

    /**
     * The price this specific cart line actually charges - a real sale on
     * the Price row always wins; failing that, a line added through the
     * bamboo-case cross-sell (cart_items.is_upsell, set once at add time by
     * CartService's server-validated eligibility check - never by the
     * client) uses the variant's admin-configured upsell_amount instead of
     * its regular price; failing that, a multi-piece pack with no sale of
     * its own falls back to bundleCompareAtUnitPrice() above. Exactly one
     * of these ever applies. Returns a transient, never-persisted Price
     * instance when a fallback applies - just a shared shape for
     * lineTotal()/CartItemResource to read amount/compare_at_amount/
     * isOnSale() from, identically to a real row.
     */
    public function effectivePrice(CartItem $item, string $currency): ?Price
    {
        $variant = $item->productVariant;
        if ($variant === null) {
            return null;
        }

        $price = $this->unitPrice($variant, $currency);
        if ($price === null) {
            return null;
        }

        if ($price->isOnSale()) {
            return $price;
        }

        if ($item->is_upsell && $price->upsell_amount !== null) {
            return new Price([
                'currency' => $currency,
                'amount' => $price->upsell_amount,
                'compare_at_amount' => $price->amount,
            ]);
        }

        $bundleCompareAt = $this->bundleCompareAtUnitPrice($variant, $currency);
        if ($bundleCompareAt !== null && $bundleCompareAt > $price->amount) {
            return new Price([
                'currency' => $currency,
                'amount' => $price->amount,
                'compare_at_amount' => $bundleCompareAt,
            ]);
        }

        return $price;
    }

    public function lineTotal(CartItem $item, string $currency): float
    {
        $price = $this->effectivePrice($item, $currency);

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
