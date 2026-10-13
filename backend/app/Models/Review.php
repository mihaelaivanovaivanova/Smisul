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
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property ReviewStatus $status
 * @property Carbon $created_at
 * @property ?Carbon $admin_reply_at
 * @property-read ?User $user guest reviews have no account - see the guest confirmation-flow migration
 * @property ?string $email
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
        'email',
        'display_name',
        'is_anonymous',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ReviewStatus::class,
            'verified_purchase' => 'boolean',
            'helpful_count' => 'integer',
            'admin_reply_at' => 'datetime',
            'is_anonymous' => 'boolean',
            'confirmed_at' => 'datetime',
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
     * Exists only once this review has been approved - IssueReviewRewardCode
     * creates it synchronously as part of handling ReviewApproved, so by the
     * time a controller that just created/approved this review returns, the
     * row (if any) is already there to load.
     *
     * @return HasOne<DiscountCode, $this>
     */
    public function discountCode(): HasOne
    {
        return $this->hasOne(DiscountCode::class);
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

    /**
     * Works for both an authenticated review (no email/display_name
     * columns set - user_id is the source of truth) and a guest review
     * (user_id null, email/display_name set directly - see the guest
     * confirmation-flow migration). Resolved via the order rather than
     * display_name for the greeting name specifically, since display_name
     * is a public-facing nickname the guest typed (could be "Anonymous",
     * an alias, anything) - not reliably a real first name the way
     * order.customer_first_name is.
     */
    public function reviewerEmail(): ?string
    {
        $user = $this->user;

        return $user !== null ? $user->email : $this->email;
    }

    public function reviewerFirstName(): string
    {
        return $this->order->customer_first_name;
    }

    public function hasReply(): bool
    {
        return $this->admin_reply !== null;
    }
}
