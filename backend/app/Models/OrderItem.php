<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A purchased line, frozen at order time. product_name/variant_name/sku/
 * unit_price/line_total are snapshots — never re-derived from the live
 * ProductVariant/Price rows, so this row stays meaningful even after the
 * product is edited, repriced, or deleted (productVariant may resolve to
 * null; every other field here is self-sufficient regardless).
 *
 * A kit line (bundle_id set) carries BOTH bundle_id and a product_variant_id
 * - the latter points at the kit's catalog shell variant (see
 * KitShellProductService), solely so the review/reminder system can treat
 * it like any other purchased product. product_name/sku/bundle_name are
 * snapshotted from the kit (see OrderService::placeOrder()), and the stock
 * it holds lives on the kit's real component variants, never the shell
 * (OrderService's bundle branches check bundle_id first). A kit line placed
 * before shells existed has a null product_variant_id.
 */
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_variant_id',
        'bundle_id',
        'bundle_name',
        'product_name',
        'variant_name',
        'sku',
        'quantity',
        'unit_price',
        'compare_at_price',
        'line_total',
        'discount_amount',
        'promotion_name',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'discount_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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

    public function isBundle(): bool
    {
        return $this->bundle_id !== null;
    }
}
