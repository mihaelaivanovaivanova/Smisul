<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One component of a ProductBundle: $quantity units of a real
 * ProductVariant per one kit.
 */
class ProductBundleItem extends Model
{
    protected $fillable = [
        'product_bundle_id',
        'product_variant_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ProductBundle, $this>
     */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ProductBundle::class, 'product_bundle_id');
    }

    /**
     * withTrashed(): a component variant soft-deleted after a kit was
     * already added to a cart/order must still resolve, so the stock it
     * holds can be released or committed against its (still existing)
     * Inventory row. Purchasability checks reject a trashed component
     * separately (see BundleService::isPurchasable()).
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class)->withTrashed();
    }
}
