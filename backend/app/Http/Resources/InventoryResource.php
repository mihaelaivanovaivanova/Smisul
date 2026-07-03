<?php

namespace App\Http\Resources;

use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Inventory
 */
class InventoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'available_quantity' => $this->availableQuantity(),
            'is_in_stock' => $this->isInStock(),
            'is_low_stock' => $this->isLowStock(),
        ];
    }
}
