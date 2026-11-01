<?php

namespace App\Services;

use App\DataTransferObjects\Cart\CartItemMutationResult;
use App\Enums\OrderStatus;
use App\Events\Cart\CartItemAdded;
use App\Exceptions\Cart\VariantNotPurchasableException;
use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\Inventory;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Kits (ProductBundle): one cart/order line at one stored price, with all
 * stock held on the kit's real component variants. This is the single
 * place that knows how to turn "N of this kit" into per-component stock
 * operations - CartService and OrderService call the *Components() methods
 * below from their own bundle branches rather than each re-deriving the
 * per-component scaling. Every inventory read-modify-write re-fetches the
 * Inventory row with lockForUpdate() first and must run inside the
 * caller's DB transaction, same rule as CartService's own docblock.
 *
 * Invariant (the kit-aware extension of CartService's): a variant's
 * quantity_reserved equals the sum of its quantity across every ordinary
 * cart_item, PLUS component.quantity x line quantity for every kit
 * cart_item containing it, PLUS the same for not-yet-confirmed order lines.
 */
class BundleService
{
    public function __construct(
        private readonly CartPricingService $pricing,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * Adds $quantity of a kit to the cart as (or onto) its single kit line,
     * reserving component.quantity x $quantity of every component. Checks
     * ALL components before reserving ANY - if even one can't cover it,
     * InsufficientStockException is thrown with nothing reserved.
     */
    public function addBundleToCart(Cart $cart, ProductBundle $bundle, int $quantity = 1): CartItemMutationResult
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Kit quantity must be at least 1.');
        }

        return DB::transaction(function () use ($cart, $bundle, $quantity) {
            $bundle = ProductBundle::with(ProductBundle::COMPONENT_EAGER_LOAD)->findOrFail($bundle->id);

            if (! $this->pricing->isBundlePurchasable($bundle)) {
                throw VariantNotPurchasableException::forSku($bundle->slug);
            }

            $cart = Cart::whereKey($cart->id)->lockForUpdate()->first() ?? $cart;
            $existing = $cart->items()->where('bundle_id', $bundle->id)->first();
            $existingQuantity = $existing !== null ? $existing->quantity : 0;
            $requestedTotal = $existingQuantity + $quantity;

            if ($requestedTotal > CartPricingService::MAX_QUANTITY_PER_ITEM) {
                throw InsufficientStockException::forVariant(
                    $bundle->slug,
                    $requestedTotal,
                    CartPricingService::MAX_QUANTITY_PER_ITEM,
                );
            }

            // Only the newly-added amount, same as CartService::addItem -
            // the existing line's own components are already reserved.
            $this->reserveComponents($bundle, $quantity);

            $wasCreated = $existing === null;

            $item = $cart->items()->updateOrCreate(
                ['bundle_id' => $bundle->id],
                ['quantity' => $requestedTotal],
            );

            CartItemAdded::dispatch($cart, $item);

            return new CartItemMutationResult($item, $wasCreated);
        });
    }

    /**
     * Reserves component.quantity x $multiplier of every component,
     * all-or-nothing: every component's Inventory row is locked and checked
     * first, and only once all of them can cover their share is anything
     * actually reserved (via InventoryService::reserve(), which re-checks).
     */
    public function reserveComponents(ProductBundle $bundle, int $multiplier): void
    {
        if ($multiplier <= 0) {
            return;
        }

        $toReserve = [];

        foreach ($bundle->items as $component) {
            $required = $component->quantity * $multiplier;
            $inventory = $this->lockInventory($component);
            $sku = $component->productVariant?->sku ?? "variant-{$component->product_variant_id}";

            if ($inventory === null) {
                throw InsufficientStockException::forVariant($sku, $required, 0);
            }

            if (! $inventory->backorders_allowed && $inventory->availableQuantity() < $required) {
                throw InsufficientStockException::forVariant($sku, $required, $inventory->availableQuantity());
            }

            $toReserve[] = [$inventory, $required];
        }

        foreach ($toReserve as [$inventory, $required]) {
            $this->inventory->reserve($inventory, $required);
        }
    }

    /**
     * Releases component.quantity x $multiplier of every component's
     * reservation - the inverse of reserveComponents().
     */
    public function releaseComponents(ProductBundle $bundle, int $multiplier): void
    {
        if ($multiplier <= 0) {
            return;
        }

        foreach ($bundle->items as $component) {
            $inventory = $this->lockInventory($component);

            if ($inventory !== null) {
                $this->inventory->release($inventory, $component->quantity * $multiplier);
            }
        }
    }

    /**
     * Converts a held kit reservation into real, permanent decrements on
     * every component - the per-component equivalent of
     * OrderService::fulfillReservation(), in the same order (decrement,
     * then release the reservation bookkeeping).
     */
    public function fulfillComponents(ProductBundle $bundle, int $multiplier): void
    {
        if ($multiplier <= 0) {
            return;
        }

        foreach ($bundle->items as $component) {
            $inventory = $this->lockInventory($component);

            if ($inventory === null) {
                continue;
            }

            $quantity = $component->quantity * $multiplier;
            $this->inventory->decreaseStock($inventory, $quantity);
            $this->inventory->release($inventory, $quantity);
        }
    }

    /**
     * Whether a kit's components are currently referenced by any held
     * stock - an open cart line, or an order line not yet confirmed/
     * cancelled. Changing the kit's components (or deleting it) while this
     * is true would make every later release/fulfill of those holds act on
     * the wrong variants, so the admin endpoints refuse it.
     */
    public function hasOutstandingReservations(ProductBundle $bundle): bool
    {
        if ($bundle->cartItems()->exists()) {
            return true;
        }

        return $bundle->orderItems()
            ->whereHas('order', fn ($query) => $query->whereIn('status', [
                OrderStatus::Pending,
                OrderStatus::AwaitingPayment,
            ]))
            ->exists();
    }

    private function lockInventory(ProductBundleItem $component): ?Inventory
    {
        return Inventory::query()
            ->where('product_variant_id', $component->product_variant_id)
            ->lockForUpdate()
            ->first();
    }
}
