<?php

namespace App\Models;

use App\Enums\VariantStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A curated kit (e.g. the 1-Person Kit) sold as ONE cart/order line at ONE
 * stored price ($amount), while its stock lives entirely on its real
 * component ProductVariants (items()) - there is deliberately no bundle-
 * level stock field. Every stock operation on a kit line (reserve, release,
 * fulfill) loops items() and applies the same operation to each
 * component's own Inventory row, scaled by the line's quantity - see
 * BundleService and the bundle branches in CartService/OrderService.
 *
 * Reuses VariantStatus (active/inactive) for its own status - same two
 * states, same meaning.
 *
 * shellProductVariant() is the kit's backing catalog entry (see
 * KitShellProductService) - never sold or stock-tracked itself, it only
 * lets the kit list in the catalog and its order lines be reviewed.
 *
 * @property VariantStatus $status
 */
class ProductBundle extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'badge',
        'currency',
        'amount',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => VariantStatus::class,
            'sort_order' => 'integer',
            'shell_product_variant_id' => 'integer',
        ];
    }

    /**
     * Everything needed to price, check, and reserve a kit's components
     * without an N+1 query per component.
     */
    public const COMPONENT_EAGER_LOAD = [
        'items.productVariant.product.primaryMedia',
        'items.productVariant.product.variants.prices',
        'items.productVariant.prices',
        'items.productVariant.inventory',
    ];

    /**
     * @return HasMany<ProductBundleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function shellProductVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'shell_product_variant_id');
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'bundle_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'bundle_id');
    }

    /**
     * @param  Builder<ProductBundle>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', VariantStatus::Active->value);
    }

    public function isActive(): bool
    {
        return $this->status === VariantStatus::Active;
    }

    /**
     * What these exact components would cost bought separately right now,
     * at each one's TRUE single-piece price - computed fresh every time,
     * never stored. For a multi-piece pack component (pack_size > 1, e.g.
     * the kit's 5-pack Miswak), this is NOT that pack's own listed price
     * (which is itself already a bulk discount vs. buying that many
     * single pieces) - it's the live 1-piece sibling's price x pack size,
     * the same non-fabricated "vs. buying singly" basis
     * CartPricingService::bundleCompareAtUnitPrice() uses for a standalone
     * pack purchase. Falls back to the component's own price when it has
     * no 1-piece sibling (e.g. the scraper/case, both pack_size 1) or
     * isn't a pack at all. Used only as the strike-through "you save"
     * figure; the kit's actual price is always $amount. A component with
     * no price in this currency contributes 0 (so the figure can only
     * ever understate the saving, never overstate it).
     */
    public function retailValue(string $currency): float
    {
        return round($this->items->sum(function (ProductBundleItem $item) use ($currency) {
            $variant = $item->productVariant;

            if ($variant === null) {
                return 0.0;
            }

            $unitPrice = $this->singlePieceUnitPrice($variant, $currency) ?? $variant->priceFor($currency)?->amount;

            return $unitPrice !== null ? (float) $unitPrice * $item->quantity : 0.0;
        }), 2);
    }

    /**
     * variant's own pack price, per piece, at its 1-piece sibling's live
     * price - null when this isn't a multi-piece pack, the product has no
     * 1-piece sibling variant, or that sibling has no price in this
     * currency (same conditions as CartPricingService::
     * bundleCompareAtUnitPrice(), which this mirrors for a single kit
     * component rather than a whole cart line).
     */
    private function singlePieceUnitPrice(ProductVariant $variant, string $currency): ?float
    {
        if ($variant->pack_size <= 1 || $variant->product === null) {
            return null;
        }

        $singleVariant = $variant->product->variants->first(
            fn (ProductVariant $candidate) => $candidate->pack_size === 1,
        );
        $singlePrice = $singleVariant?->priceFor($currency);

        return $singlePrice !== null ? (float) $singlePrice->amount * $variant->pack_size : null;
    }
}
