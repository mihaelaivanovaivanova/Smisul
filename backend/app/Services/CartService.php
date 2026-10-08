<?php

namespace App\Services;

use App\DataTransferObjects\Cart\AddCartItemData;
use App\DataTransferObjects\Cart\CartItemMutationResult;
use App\DataTransferObjects\Cart\UpdateCartItemData;
use App\Enums\Currency;
use App\Events\Cart\CartCleared;
use App\Events\Cart\CartItemAdded;
use App\Events\Cart\CartItemRemoved;
use App\Events\Cart\CartItemUpdated;
use App\Events\Cart\CartMerged;
use App\Exceptions\Cart\CartItemNotFoundException;
use App\Exceptions\Cart\VariantNotPurchasableException;
use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates cart resolution (guest/user, including merge-on-login) and
 * mutations. All pricing/availability math is delegated to
 * CartPricingService, and all inventory reservation is delegated to
 * InventoryService — kept separate so both can be extended (discounts,
 * shipping, tax; reservation TTLs/expiry) without touching this class.
 *
 * Every mutation here keeps one invariant true: a variant's
 * Inventory::quantity_reserved always equals the sum of that variant's
 * quantity across every cart_item in the database. Adding reserves,
 * removing/reducing releases, and merging never changes the total (it only
 * ever reduces it, and only when the absolute per-item cap forces a line
 * to shrink — see mergeGuestCartIfExists). Every actual read-modify-write
 * of quantity_reserved re-fetches the Inventory row with lockForUpdate()
 * immediately beforehand, inside the enclosing transaction — eager-loaded
 * relations are for display/error-message purposes only and are never
 * used as the basis for a reservation change.
 */
class CartService
{
    /**
     * Everything CartResource/CartItemResource need to render a cart
     * without N+1 queries. Kept as a single source of truth so callers
     * never have to remember (or risk clobbering) it with an ad-hoc load().
     */
    public const EAGER_LOAD = [
        'items.productVariant.product.primaryMedia',
        // The sibling pack_size===1 variant + its prices — needed for the
        // bundle "was" price CartPricingService::bundleCompareAtUnitPrice()
        // computes (same non-fabricated basis as funnelOffers.ts's
        // computeOriginalPrice on the frontend), without an N+1 query per line.
        'items.productVariant.product.variants.prices',
        'items.productVariant.media',
        'items.productVariant.prices',
        'items.productVariant.inventory',
    ];

    /**
     * The cross-sell pairings this store has: adding any of these products
     * through an upsell card (cart drawer, checkout review, or the bamboo
     * case's own product page — see frontend's UpsellCard.tsx) is only ever
     * honored at its upsell_amount when the cart already contains a real
     * Miswak item. Order matters: it's the order offers are returned in
     * (see upsellOffers() below). A simple hardcoded list, not a general
     * feature — adding a product here also requires setting its variant's
     * upsell_amount via the admin (Admin\PriceResource), or it's silently
     * skipped (see upsellOffers()'s own null check).
     *
     * @var list<string>
     */
    private const UPSELL_PRODUCT_SLUGS = [
        'bambukov-keis-za-miswak',
        'stargalka-za-ezik',
    ];

    private const UPSELL_MISWAK_SLUG = 'miswak';

    public function __construct(
        private readonly CartPricingService $pricing,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * Finds or creates the cart for this request. Authenticated users
     * always get their own cart; if a guest cart token is also present
     * (the browser had items before logging in), it's merged in and then
     * discarded. Guests get their cart by token, minting a fresh one when
     * no token was supplied.
     */
    public function resolveCart(?int $userId, ?string $guestToken): Cart
    {
        if ($userId !== null) {
            $cart = Cart::firstOrCreate(['user_id' => $userId], ['currency' => Currency::EUR->value]);

            if ($guestToken !== null) {
                $this->mergeGuestCartIfExists($cart, $guestToken);
            }

            return $cart;
        }

        $guestToken ??= (string) Str::uuid();

        return Cart::firstOrCreate(['guest_token' => $guestToken], ['currency' => Currency::EUR->value]);
    }

    public function addItem(Cart $cart, AddCartItemData $data): CartItemMutationResult
    {
        return DB::transaction(function () use ($cart, $data) {
            $variant = ProductVariant::with('product')->findOrFail($data->productVariantId);

            if (! $this->pricing->isVariantPurchasable($variant)) {
                throw VariantNotPurchasableException::forSku($variant->sku);
            }

            $cart = Cart::whereKey($cart->id)->lockForUpdate()->first() ?? $cart;
            $existing = $cart->items()->where('product_variant_id', $variant->id)->first();
            $existingQuantity = $existing !== null ? $existing->quantity : 0;
            $requestedTotal = $existingQuantity + $data->quantity;

            if ($requestedTotal > CartPricingService::MAX_QUANTITY_PER_ITEM) {
                throw InsufficientStockException::forVariant(
                    $variant->sku,
                    $requestedTotal,
                    CartPricingService::MAX_QUANTITY_PER_ITEM,
                );
            }

            $inventory = $variant->inventory()->lockForUpdate()->first();

            if ($inventory === null) {
                throw InsufficientStockException::forVariant($variant->sku, $data->quantity, 0);
            }

            // Reserves exactly the newly-added amount, validated against
            // whatever is genuinely free right now across every cart — not
            // the running total, so this cart's own prior reservation for
            // this line is never double-counted against itself.
            $this->inventory->reserve($inventory, $data->quantity);

            $wasCreated = $existing === null;

            // is_upsell is only ever set when this line is first created —
            // a later re-add/merge (updateOrCreate's update branch) must
            // never flip it either way, so its price basis can't change
            // out from under an existing line.
            $values = ['quantity' => $requestedTotal];
            if ($wasCreated) {
                $values['is_upsell'] = $data->isUpsell && $this->isEligibleForUpsellPrice($cart, $variant);
            }

            $item = $cart->items()->updateOrCreate(
                ['product_variant_id' => $variant->id],
                $values,
            );

            CartItemAdded::dispatch($cart, $item);

            return new CartItemMutationResult($item, $wasCreated);
        });
    }

    public function updateItemQuantity(Cart $cart, int $cartItemId, UpdateCartItemData $data): void
    {
        DB::transaction(function () use ($cart, $cartItemId, $data) {
            $item = $cart->items()->with('productVariant')->lockForUpdate()->find($cartItemId);

            if ($item === null) {
                throw CartItemNotFoundException::forId($cartItemId);
            }

            if ($data->quantity > CartPricingService::MAX_QUANTITY_PER_ITEM) {
                $variant = $item->productVariant;
                $sku = $variant !== null ? $variant->sku : 'unknown';

                throw InsufficientStockException::forVariant($sku, $data->quantity, CartPricingService::MAX_QUANTITY_PER_ITEM);
            }

            $this->adjustReservation($item, $data->quantity);

            $item->update(['quantity' => $data->quantity]);

            CartItemUpdated::dispatch($cart, $item);
        });
    }

    public function removeItem(Cart $cart, int $cartItemId): void
    {
        DB::transaction(function () use ($cart, $cartItemId) {
            $item = $cart->items()->with('productVariant.product')->lockForUpdate()->find($cartItemId);

            if ($item === null) {
                throw CartItemNotFoundException::forId($cartItemId);
            }

            $this->releaseReservation($item);

            $removedProductSlug = $item->productVariant?->product?->slug;
            $productVariantId = $item->product_variant_id;
            $item->delete();

            if ($removedProductSlug === self::UPSELL_MISWAK_SLUG) {
                $this->revokeUpsellPricesIfMiswakGone($cart);
            }

            CartItemRemoved::dispatch($cart, $productVariantId);
        });
    }

    public function clear(Cart $cart): void
    {
        DB::transaction(function () use ($cart) {
            $items = $cart->items()->with('productVariant')->lockForUpdate()->get();

            foreach ($items as $item) {
                $this->releaseReservation($item);
            }

            $cart->items()->delete();

            CartCleared::dispatch($cart);
        });
    }

    /**
     * Moves a cart_item's reservation from its current quantity to a new
     * one — reserving the delta if it grew (throwing if stock can't cover
     * it), or releasing the delta if it shrank.
     */
    private function adjustReservation(CartItem $item, int $newQuantity): void
    {
        $delta = $newQuantity - $item->quantity;

        if ($delta === 0) {
            return;
        }

        $variant = $item->productVariant;

        if ($variant === null) {
            if ($delta > 0) {
                throw InsufficientStockException::forVariant('unknown', $newQuantity, 0);
            }

            return;
        }

        $inventory = $variant->inventory()->lockForUpdate()->first();

        if ($inventory === null) {
            if ($delta > 0) {
                throw InsufficientStockException::forVariant($variant->sku, $newQuantity, 0);
            }

            return;
        }

        if ($delta > 0) {
            $this->inventory->reserve($inventory, $delta);
        } else {
            $this->inventory->release($inventory, -$delta);
        }
    }

    private function releaseReservation(CartItem $item): void
    {
        $variant = $item->productVariant;

        if ($variant === null) {
            return;
        }

        $inventory = $variant->inventory()->lockForUpdate()->first();

        if ($inventory !== null) {
            $this->inventory->release($inventory, $item->quantity);
        }
    }

    /**
     * Merges a guest cart (identified by token) into an authenticated
     * user's cart: quantities for variants present in both are summed and
     * variants only in the guest cart are copied over as new lines — never
     * losing an item. Both quantities were already independently reserved
     * against the shared inventory row when they were first added, so
     * merging the lines together doesn't change the total reservation *at
     * all*, with one exception: if the sum exceeds the absolute per-item
     * cap, the trimmed excess is released back to inventory since no line
     * represents it anymore. Locks both cart rows for the duration to
     * avoid a lost update if the same guest cart were merged concurrently
     * (e.g. two tabs).
     */
    private function mergeGuestCartIfExists(Cart $userCart, string $guestToken): void
    {
        DB::transaction(function () use ($userCart, $guestToken) {
            $guestCart = Cart::where('guest_token', $guestToken)
                ->with('items.productVariant')
                ->lockForUpdate()
                ->first();

            if ($guestCart === null || $guestCart->id === $userCart->id) {
                return;
            }

            $lockedUserCart = Cart::whereKey($userCart->id)->lockForUpdate()->first() ?? $userCart;
            $mergedCount = 0;

            foreach ($guestCart->items as $guestItem) {
                $variant = $guestItem->productVariant;

                if ($variant === null) {
                    continue;
                }

                $existing = $lockedUserCart->items()->where('product_variant_id', $variant->id)->first();
                $existingQuantity = $existing !== null ? $existing->quantity : 0;
                $combinedQuantity = $existingQuantity + $guestItem->quantity;
                $cappedQuantity = min($combinedQuantity, CartPricingService::MAX_QUANTITY_PER_ITEM);
                $excess = $combinedQuantity - $cappedQuantity;

                if ($excess > 0) {
                    $inventory = $variant->inventory()->lockForUpdate()->first();

                    if ($inventory !== null) {
                        $this->inventory->release($inventory, $excess);
                    }
                }

                $lockedUserCart->items()->updateOrCreate(
                    ['product_variant_id' => $variant->id],
                    $existing !== null
                        ? ['quantity' => $cappedQuantity]
                        : ['quantity' => $cappedQuantity, 'is_upsell' => $guestItem->is_upsell],
                );

                $mergedCount++;
            }

            $guestCart->delete();

            CartMerged::dispatch($lockedUserCart, $mergedCount);
        });
    }

    /**
     * Server-side truth for whether this add-to-cart request may actually
     * be priced as an upsell — never just trusts the request's is_upsell
     * flag (see AddCartItemRequest's own comment). True only when the
     * variant being added belongs to one of UPSELL_PRODUCT_SLUGS and the
     * cart already contains a real Miswak item, the same condition
     * upsellOffers() below uses to decide which offers to show.
     */
    private function isEligibleForUpsellPrice(Cart $cart, ProductVariant $variant): bool
    {
        if (! in_array($variant->product?->slug, self::UPSELL_PRODUCT_SLUGS, true)) {
            return false;
        }

        return $this->cartHasProduct($cart, self::UPSELL_MISWAK_SLUG);
    }

    private function cartHasProduct(Cart $cart, string $productSlug): bool
    {
        return $cart->items()
            ->whereHas('productVariant.product', fn ($query) => $query->where('slug', $productSlug))
            ->exists();
    }

    /**
     * Keeps CartItem::is_upsell honest after a Miswak item is removed — an
     * upsell product's upsell_amount is only ever justified by a real
     * Miswak item genuinely being in the same cart (see
     * isEligibleForUpsellPrice() above), so any upsell-priced line must
     * revert to its regular price the moment the last Miswak item leaves,
     * not keep a discount that no longer applies. A no-op if the cart
     * still has another Miswak line (e.g. two different pack sizes) or no
     * upsell product line is upsell-priced.
     */
    private function revokeUpsellPricesIfMiswakGone(Cart $cart): void
    {
        if ($this->cartHasProduct($cart, self::UPSELL_MISWAK_SLUG)) {
            return;
        }

        $cart->items()
            ->whereHas('productVariant.product', fn ($query) => $query->whereIn('slug', self::UPSELL_PRODUCT_SLUGS))
            ->where('is_upsell', true)
            ->update(['is_upsell' => false]);
    }

    /**
     * Every cross-sell offer eligible for this cart right now, in
     * UPSELL_PRODUCT_SLUGS order — empty once the cart has no Miswak item
     * yet. Each product is then considered independently: already in the
     * cart, not found, or no upsell_amount configured for it (set via the
     * admin product price form — see Admin\PriceResource) all just skip
     * that one product rather than short-circuiting the rest. Single
     * source of truth for both what UpsellCard.tsx displays (cart drawer,
     * checkout review, and the bamboo case's own product page) and what
     * addItem() above actually charges — isEligibleForUpsellPrice() reuses
     * the exact same list, so an offer never gets shown that wouldn't also
     * be honored at add time.
     *
     * @return list<array{product: Product, variant: ProductVariant, price: Price}>
     */
    public function upsellOffers(Cart $cart): array
    {
        if (! $this->cartHasProduct($cart, self::UPSELL_MISWAK_SLUG)) {
            return [];
        }

        $offers = [];

        foreach (self::UPSELL_PRODUCT_SLUGS as $slug) {
            if ($this->cartHasProduct($cart, $slug)) {
                continue;
            }

            $product = Product::with(['variants.prices', 'variants.inventory', 'primaryMedia'])
                ->where('slug', $slug)
                ->first();

            $variant = $product?->variants->firstWhere('is_default', true) ?? $product?->variants->first();
            $price = $variant?->priceFor($cart->currency);

            if ($product === null || $variant === null || $price === null || $price->upsell_amount === null) {
                continue;
            }

            $offers[] = ['product' => $product, 'variant' => $variant, 'price' => $price];
        }

        return $offers;
    }
}
