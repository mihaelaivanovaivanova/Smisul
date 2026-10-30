<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $code
 * @property ?string $customer_email the email that originally earned this code (see IssueReviewRewardCode) - a record only, not a redemption gate: DiscountCodeService never checks it, so any valid code is redeemable by whoever has it
 * @property float $discount_percent
 * @property int $max_uses
 * @property int $used_count
 * @property ?Carbon $starts_at
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
        'starts_at',
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
            'starts_at' => 'datetime',
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

    public function isRedeemable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && Carbon::now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at !== null && Carbon::now()->gt($this->expires_at)) {
            return false;
        }

        return $this->used_count < $this->max_uses;
    }
}
