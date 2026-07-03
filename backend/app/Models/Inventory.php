<?php

namespace App\Models;

use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    /** @use HasFactory<InventoryFactory> */
    use HasFactory;

    protected $table = 'inventories';

    protected $fillable = [
        'product_variant_id',
        'quantity_on_hand',
        'quantity_reserved',
        'low_stock_threshold',
        'backorders_allowed',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
            'low_stock_threshold' => 'integer',
            'backorders_allowed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function availableQuantity(): int
    {
        return max(0, $this->quantity_on_hand - $this->quantity_reserved);
    }

    public function isLowStock(): bool
    {
        return $this->availableQuantity() <= $this->low_stock_threshold;
    }

    public function isInStock(): bool
    {
        return $this->availableQuantity() > 0 || $this->backorders_allowed;
    }
}
