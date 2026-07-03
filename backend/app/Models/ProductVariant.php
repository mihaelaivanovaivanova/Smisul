<?php

namespace App\Models;

use App\Enums\VariantStatus;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property VariantStatus $status
 */
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'pack_size',
        'barcode',
        'is_default',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'pack_size' => 'integer',
            'is_default' => 'boolean',
            'status' => VariantStatus::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasOne<Inventory, $this>
     */
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    /**
     * @return HasMany<Price, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    /**
     * @return HasMany<PriceHistory, $this>
     */
    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function priceFor(string $currency): ?Price
    {
        return $this->prices->firstWhere('currency', $currency);
    }

    public function isActive(): bool
    {
        return $this->status === VariantStatus::Active;
    }
}
