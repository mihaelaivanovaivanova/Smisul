<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Carbon\Carbon;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ReviewStatus $status
 * @property Carbon $created_at
 * @property ?Carbon $admin_reply_at
 */
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'product_id',
        'product_variant_id',
        'rating',
        'title',
        'body',
        'status',
        'verified_purchase',
        'admin_reply',
        'admin_reply_at',
        'admin_replied_by',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ReviewStatus::class,
            'verified_purchase' => 'boolean',
            'helpful_count' => 'integer',
            'admin_reply_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
    public function adminRepliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_replied_by');
    }

    /**
     * @return HasMany<ReviewVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(ReviewVote::class);
    }

    /**
     * Future review-images architecture (see the review_media migration) —
     * nothing populates this yet.
     *
     * @return HasMany<ReviewMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(ReviewMedia::class);
    }

    /**
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::Approved);
    }

    public function isPending(): bool
    {
        return $this->status === ReviewStatus::Pending;
    }

    public function hasReply(): bool
    {
        return $this->admin_reply !== null;
    }
}
