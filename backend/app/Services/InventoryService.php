<?php

namespace App\Services;

use App\Events\Favorite\ProductBackInStock;
use App\Exceptions\InsufficientStockException;
use App\Models\Inventory;

class InventoryService
{
    public function increaseStock(Inventory $inventory, int $quantity): Inventory
    {
        $wasInStock = $inventory->isInStock();

        $inventory->increment('quantity_on_hand', $quantity);
        $inventory->refresh();

        $this->dispatchBackInStockIfNeeded($inventory, $wasInStock);

        return $inventory;
    }

    public function decreaseStock(Inventory $inventory, int $quantity): Inventory
    {
        $this->ensureAvailable($inventory, $quantity);

        $inventory->decrement('quantity_on_hand', $quantity);

        return $inventory->refresh();
    }

    /**
     * Sets the on-hand quantity to an absolute value — an admin's stock
     * recount/correction, not a fulfillment-driven delta. Deliberately
     * skips ensureAvailable(): an admin asserting ground truth (e.g. "we
     * actually only have 5, even though 8 are reserved by pending carts")
     * is a legitimate override, not a race condition to block.
     */
    public function setQuantityOnHand(Inventory $inventory, int $quantity): Inventory
    {
        $wasInStock = $inventory->isInStock();

        $inventory->quantity_on_hand = max(0, $quantity);
        $inventory->save();
        $inventory->refresh();

        $this->dispatchBackInStockIfNeeded($inventory, $wasInStock);

        return $inventory;
    }

    private function dispatchBackInStockIfNeeded(Inventory $inventory, bool $wasInStock): void
    {
        if (! $wasInStock && $inventory->isInStock()) {
            event(new ProductBackInStock($inventory->productVariant));
        }
    }

    /**
     * Reserve stock for a pending order (e.g. once Checkout exists). Reserved
     * stock is still on_hand but no longer counted as available.
     */
    public function reserve(Inventory $inventory, int $quantity): Inventory
    {
        $this->ensureAvailable($inventory, $quantity);

        $inventory->increment('quantity_reserved', $quantity);

        return $inventory->refresh();
    }

    public function release(Inventory $inventory, int $quantity): Inventory
    {
        $inventory->quantity_reserved = max(0, $inventory->quantity_reserved - $quantity);
        $inventory->save();

        return $inventory;
    }

    private function ensureAvailable(Inventory $inventory, int $quantity): void
    {
        if ($inventory->backorders_allowed) {
            return;
        }

        if ($inventory->availableQuantity() < $quantity) {
            throw InsufficientStockException::forVariant(
                $inventory->productVariant->sku,
                $quantity,
                $inventory->availableQuantity(),
            );
        }
    }
}
