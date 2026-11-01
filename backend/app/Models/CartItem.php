<?php

namespace App\Models;

use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_variant_id',
        'bundle_id',
        'quantity',
        'is_upsell',
    ];

    /**
     * A line is exactly one of: an ordinary product line (product_variant_id)
     * or a kit line (bundle_id) - never both, never neither. Every stock
     * operation branches on which one is set (see CartService/BundleService),
     * so a row with both or neither would have no well-defined reservation.
     */
    protected static function booted(): void
    {
        static::saving(function (CartItem $item) {
            if (($item->product_variant_id === null) === ($item->bundle_id === null)) {
                throw new LogicException('A cart item must have exactly one of product_variant_id or bundle_id.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_upsell' => 'boolean',
        ];
    }

    public function isBundle(): bool
    {
        return $this->bundle_id !== null;
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * @return BelongsTo<ProductBundle, $this>
     */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ProductBundle::class, 'bundle_id');
    }
}
