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
        'items.productVariant.prices',
        'items.productVariant.inventory',
    ];

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

            $item = $cart->items()->updateOrCreate(
                ['product_variant_id' => $variant->id],
                ['quantity' => $requestedTotal],
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
            $item = $cart->items()->with('productVariant')->lockForUpdate()->find($cartItemId);

            if ($item === null) {
                throw CartItemNotFoundException::forId($cartItemId);
            }

            $this->releaseReservation($item);

            $productVariantId = $item->product_variant_id;
            $item->delete();

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
                    ['quantity' => $cappedQuantity],
                );

                $mergedCount++;
            }

            $guestCart->delete();

            CartMerged::dispatch($lockedUserCart, $mergedCount);
        });
    }
}
