<?php

namespace App\Models;

use Database\Factories\PriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Price extends Model
{
    /** @use HasFactory<PriceFactory> */
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'currency',
        'amount',
        'compare_at_amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'compare_at_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function isOnSale(): bool
    {
        return $this->compare_at_amount !== null && $this->compare_at_amount > $this->amount;
    }
}
