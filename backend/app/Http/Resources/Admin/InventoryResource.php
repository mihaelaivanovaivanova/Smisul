<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\InventoryResource as BaseInventoryResource;
use Illuminate\Http\Request;

/**
 * Adds the raw quantity_on_hand/quantity_reserved columns behind
 * available_quantity (= on_hand - reserved, see Inventory::
 * availableQuantity()) — deliberately kept out of the public/base
 * InventoryResource (a customer has no reason to see "reserved"), but an
 * admin needs both numbers to tell "truly out of stock" apart from
 * "on hand but all of it is reserved by carts right now" (see
 * ProductVariantController::reservations()) — otherwise an admin editing
 * stock only ever sees available_quantity and has no way to know there's a
 * real on-hand count being masked by stale reservations.
 */
class InventoryResource extends BaseInventoryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'quantity_on_hand' => $this->quantity_on_hand,
            'quantity_reserved' => $this->quantity_reserved,
        ];
    }
}
