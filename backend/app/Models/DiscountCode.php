<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $code
 * @property ?string $customer_email
 * @property float $discount_percent
 * @property int $max_uses
 * @property int $used_count
 * @property ?Carbon $expires_at
 * @property bool $is_active
 */
class DiscountCode extends Model
{
    protected $fillable = [
        'code',
        'customer_email',
        'discount_percent',
        'max_uses',
        'used_count',
        'expires_at',
        'is_active',
        'review_id',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * Doesn't check customer_email - DiscountCodeService::findRedeemable()
     * does that comparison (case-insensitively), since "whose email" isn't
     * something this model alone can judge.
     */
    public function isRedeemable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at !== null && Carbon::now()->gt($this->expires_at)) {
            return false;
        }

        return $this->used_count < $this->max_uses;
    }
}
