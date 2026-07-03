<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Inventory;

class InventoryService
{
    public function increaseStock(Inventory $inventory, int $quantity): Inventory
    {
        $inventory->increment('quantity_on_hand', $quantity);

        return $inventory->refresh();
    }

    public function decreaseStock(Inventory $inventory, int $quantity): Inventory
    {
        $this->ensureAvailable($inventory, $quantity);

        $inventory->decrement('quantity_on_hand', $quantity);

        return $inventory->refresh();
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
