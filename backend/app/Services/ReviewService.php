<?php

namespace App\Services;

use App\DataTransferObjects\Admin\ReviewFilterData;
use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Events\Review\ReviewApproved;
use App\Events\Review\ReviewRejected;
use App\Events\Review\ReviewReplied;
use App\Exceptions\ReviewNotEligibleException;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\ReviewVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    private const PUBLIC_EAGER_LOAD = ['user'];

    private const ADMIN_EAGER_LOAD = ['user', 'product', 'adminRepliedBy'];

    /**
     * All three rules from the sprint brief, checked in a fixed order so
     * the error message tells the customer the *first* thing standing in
     * their way rather than a generic rejection:
     *  1. the order is theirs
     *  2. the order actually contains this variant
     *  3. the order has reached Delivered
     *  4. this order+product hasn't already been reviewed
     */
    public function assertEligible(User $user, ProductVariant $variant, Order $order): void
    {
        if ($order->user_id !== $user->id) {
            throw ReviewNotEligibleException::notPurchased();
        }

        $purchased = $order->items()->where('product_variant_id', $variant->id)->exists();

        if (! $purchased) {
            throw ReviewNotEligibleException::notPurchased();
        }

        if ($order->status !== OrderStatus::Delivered) {
            throw ReviewNotEligibleException::notDelivered();
        }

        $alreadyReviewed = Review::query()
            ->where('order_id', $order->id)
            ->where('product_id', $variant->product_id)
            ->exists();

        if ($alreadyReviewed) {
            throw ReviewNotEligibleException::alreadyReviewed();
        }
    }

    /**
     * Reviews publish immediately — there is no pre-publication moderation
     * queue. Admins moderate after the fact (hide/reject/delete a live
     * review, or restore one via approve) rather than gatekeeping before
     * publication; see ReviewPolicy for the matching customer-side rule
     * (owners may edit/delete their review at any time, not just pre-review).
     *
     * @param  array{rating: int, title: string, body: string}  $data
     */
    public function create(User $user, ProductVariant $variant, Order $order, array $data): Review
    {
        $this->assertEligible($user, $variant, $order);

        $review = Review::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'rating' => $data['rating'],
            'title' => $data['title'],
            'body' => $data['body'],
            'status' => ReviewStatus::Approved,
            'verified_purchase' => true,
        ]);

        return $review->load(self::PUBLIC_EAGER_LOAD);
    }

    /**
     * @param  array{rating?: int, title?: string, body?: string}  $data
     */
    public function update(Review $review, array $data): Review
    {
        $review->update($data);

        return $review->load(self::PUBLIC_EAGER_LOAD);
    }

    public function delete(Review $review): void
    {
        $review->delete();
    }

    /**
     * @return LengthAwarePaginator<int, Review>
     */
    public function listForProduct(Product $product, string $sort, int $page, int $perPage = 10): LengthAwarePaginator
    {
        $query = Review::query()
            ->where('product_id', $product->id)
            ->approved()
            ->with(self::PUBLIC_EAGER_LOAD);

        match ($sort) {
            'highest' => $query->orderByDesc('rating')->latest(),
            'lowest' => $query->orderBy('rating')->latest(),
            'helpful' => $query->orderByDesc('helpful_count')->latest(),
            default => $query->latest(),
        };

        return $query->paginate($perPage, page: $page);
    }

    /**
     * @return array{average_rating: float, review_count: int, verified_count: int, distribution: array<int, int>}
     */
    public function summaryFor(Product $product): array
    {
        $approved = Review::query()->where('product_id', $product->id)->approved();

        $count = (clone $approved)->count();
        $average = $count > 0 ? round((float) (clone $approved)->avg('rating'), 2) : 0.0;
        $verifiedCount = (clone $approved)->where('verified_purchase', true)->count();

        $distributionRaw = (clone $approved)
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        $distribution = [];
        for ($star = 5; $star >= 1; $star--) {
            $distribution[$star] = (int) ($distributionRaw[$star] ?? 0);
        }

        return [
            'average_rating' => $average,
            'review_count' => $count,
            'verified_count' => $verifiedCount,
            'distribution' => $distribution,
        ];
    }

    /**
     * @return Collection<int, Review>
     */
    public function listForUser(User $user): Collection
    {
        return Review::query()
            ->where('user_id', $user->id)
            ->with(['product'])
            ->latest()
            ->get();
    }

    /**
     * @return LengthAwarePaginator<int, Review>
     */
    public function listForAdmin(ReviewFilterData $filters): LengthAwarePaginator
    {
        $query = Review::query()->with(self::ADMIN_EAGER_LOAD);

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->productId !== null) {
            $query->where('product_id', $filters->productId);
        }

        if ($filters->rating !== null) {
            $query->where('rating', $filters->rating);
        }

        if ($filters->search !== null && $filters->search !== '') {
            $term = "%{$filters->search}%";
            $query->where(function ($query) use ($term) {
                $query->where('title', 'like', $term)
                    ->orWhere('body', 'like', $term)
                    ->orWhereHas('user', function ($query) use ($term) {
                        $query->where('email', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term);
                    });
            });
        }

        match ($filters->sort) {
            'oldest' => $query->oldest(),
            'highest' => $query->orderByDesc('rating')->latest(),
            'lowest' => $query->orderBy('rating')->latest(),
            'helpful' => $query->orderByDesc('helpful_count')->latest(),
            default => $query->latest(),
        };

        return $query->paginate($filters->perPage, page: $filters->page);
    }

    public function approve(Review $review): Review
    {
        $review->update(['status' => ReviewStatus::Approved]);

        event(new ReviewApproved($review));

        return $review->load(self::ADMIN_EAGER_LOAD);
    }

    public function reject(Review $review, ?string $reason = null): Review
    {
        $review->update(['status' => ReviewStatus::Rejected]);

        event(new ReviewRejected($review, $reason));

        return $review->load(self::ADMIN_EAGER_LOAD);
    }

    /**
     * Hidden is a quiet takedown (e.g. after the fact, for a TOS violation
     * spotted post-approval) — unlike reject, it doesn't notify the author.
     */
    public function hide(Review $review): Review
    {
        $review->update(['status' => ReviewStatus::Hidden]);

        return $review->load(self::ADMIN_EAGER_LOAD);
    }

    public function reply(Review $review, User $admin, string $reply): Review
    {
        $review->update([
            'admin_reply' => $reply,
            'admin_reply_at' => now(),
            'admin_replied_by' => $admin->id,
        ]);

        event(new ReviewReplied($review));

        return $review->load(self::ADMIN_EAGER_LOAD);
    }

    /**
     * Architecture for bulk moderation: one status applied to many reviews
     * in a single request. Each row still goes through approve()/reject()/
     * hide() individually so per-review notifications and events fire
     * exactly as they would one at a time.
     *
     * @param  list<int>  $reviewIds
     */
    public function bulkModerate(array $reviewIds, ReviewStatus $status): int
    {
        $reviews = Review::query()->whereIn('id', $reviewIds)->get();

        foreach ($reviews as $review) {
            match ($status) {
                ReviewStatus::Approved => $this->approve($review),
                ReviewStatus::Rejected => $this->reject($review),
                ReviewStatus::Hidden => $this->hide($review),
                ReviewStatus::Pending => null,
            };
        }

        return $reviews->count();
    }

    /**
     * @return array{total_reviews: int, reviews_by_status: array<string, int>, average_rating: float, pending_count: int}
     */
    public function statistics(): array
    {
        $byStatus = Review::query()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');

        $averageRating = Review::query()->approved()->avg('rating');

        return [
            'total_reviews' => array_sum($byStatus->all()),
            'reviews_by_status' => collect(ReviewStatus::cases())
                ->mapWithKeys(fn (ReviewStatus $status) => [$status->value => (int) ($byStatus[$status->value] ?? 0)])
                ->all(),
            'average_rating' => $averageRating !== null ? round((float) $averageRating, 2) : 0.0,
            'pending_count' => (int) ($byStatus[ReviewStatus::Pending->value] ?? 0),
        ];
    }

    /**
     * Toggles the current user's helpful vote — voting again withdraws it,
     * rather than stacking. helpful_count is a denormalized counter kept in
     * lockstep with the review_votes rows inside a transaction so the two
     * never drift apart under concurrent votes.
     *
     * @return array{is_helpful: bool, helpful_count: int}
     */
    public function markHelpful(Review $review, User $user): array
    {
        return DB::transaction(function () use ($review, $user) {
            $vote = ReviewVote::query()
                ->where('review_id', $review->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($vote !== null) {
                $vote->delete();
                $review->decrement('helpful_count');
                $isHelpful = false;
            } else {
                ReviewVote::create(['review_id' => $review->id, 'user_id' => $user->id]);
                $review->increment('helpful_count');
                $isHelpful = true;
            }

            return ['is_helpful' => $isHelpful, 'helpful_count' => $review->fresh()->helpful_count];
        });
    }
}
