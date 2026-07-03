<?php

namespace App\Models;

use Database\Factories\PriceHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    /** @use HasFactory<PriceHistoryFactory> */
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'currency',
        'old_amount',
        'new_amount',
        'changed_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'old_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
