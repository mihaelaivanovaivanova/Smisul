<?php

namespace App\Services;

use App\DataTransferObjects\Admin\ReviewFilterData;
use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Events\Review\ReviewApproved;
use App\Events\Review\ReviewRejected;
use App\Events\Review\ReviewReplied;
use App\Exceptions\ReviewNotEligibleException;
use App\Mail\ReviewConfirmationMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\ReviewVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

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
     * Powers the storefront's "Add a review" flow — finds the most recent
     * Delivered order for this product whose customer_email matches
     * (case-insensitively), regardless of whether that order belongs to a
     * registered account or was placed as a guest (orders.customer_email is
     * always populated either way — see OrderService::create()). This is
     * what lets a guest checkout be reviewed at all: guest orders have no
     * user_id, so an auth-session-based lookup could never find them.
     * Mirrors assertEligible's own three rules (purchased, delivered, not
     * already reviewed) as a query instead of a per-order check, since here
     * there's no single order in hand yet — this finds one. Returns the
     * order_id and the specific variant of this product that order actually
     * contains, or null if no such order exists.
     *
     * The "not already reviewed" rule is per email+product here, not just
     * per order+product (unlike assertEligible, which only ever sees one
     * order at a time) — by request, one review per product per email, even
     * if that email has several delivered orders containing it. Checked
     * before confirmation too: a still-unconfirmed submission already
     * counts as "written", so a stray retry can't queue up a second one
     * waiting on the same or a different email link.
     *
     * @return array{order_id: int, product_variant_id: int}|null
     */
    public function findEligibleOrderForEmail(string $email, Product $product): ?array
    {
        $alreadyReviewed = Review::query()
            ->where('product_id', $product->id)
            ->where(DB::raw('LOWER(email)'), Str::lower($email))
            ->exists();

        if ($alreadyReviewed) {
            return null;
        }

        $variantIds = $product->variants()->pluck('id');

        $order = Order::query()
            ->where(DB::raw('LOWER(customer_email)'), Str::lower($email))
            ->where('status', OrderStatus::Delivered)
            ->whereHas('items', fn ($query) => $query->whereIn('product_variant_id', $variantIds))
            ->latest()
            ->first();

        if (! $order) {
            return null;
        }

        $variantId = $order->items()->whereIn('product_variant_id', $variantIds)->value('product_variant_id');

        return ['order_id' => $order->id, 'product_variant_id' => $variantId];
    }

    /**
     * Entry point for the guest-friendly "Add a review" wizard. Never
     * reveals whether the typed email actually matched anything — the
     * caller (ProductController::submitReview) returns the same generic
     * "check your email" response either way, so this method's return type
     * is void rather than a success/failure result that could leak through.
     * A non-matching email is a silent no-op: no review row, no email sent.
     *
     * The review is created immediately (status Pending, confirmed_at null)
     * rather than staged elsewhere, so the existing unique(order_id,
     * product_id) constraint still prevents a duplicate submission outright,
     * and confirming later is just a two-column update via confirm().
     *
     * $preVerified skips that double opt-in entirely: set only when
     * ProductController::submitReview() has already cryptographically
     * re-verified the submission came through the 30-day reminder email's
     * own signed link (see its hasValidReviewIdentityLink()) - the
     * customer already proved they control that inbox by clicking a link
     * we sent there, which is at least as strong a proof as clicking a
     * confirmation link would be, so asking them to confirm a second time
     * is pure friction. The typed-email guest wizard (no such proof) always
     * gets $preVerified=false and keeps the existing confirm-by-email step.
     *
     * @param  array{rating: int, title?: ?string, body: string, email: string, display_name: string, is_anonymous?: bool}  $data
     */
    public function submitForConfirmation(Product $product, array $data, bool $preVerified = false): void
    {
        $eligible = $this->findEligibleOrderForEmail($data['email'], $product);

        if ($eligible === null) {
            return;
        }

        $review = Review::create([
            'order_id' => $eligible['order_id'],
            'product_id' => $product->id,
            'product_variant_id' => $eligible['product_variant_id'],
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'email' => $data['email'],
            'display_name' => $data['display_name'],
            'is_anonymous' => $data['is_anonymous'] ?? false,
            'status' => $preVerified ? ReviewStatus::Approved : ReviewStatus::Pending,
            'confirmed_at' => $preVerified ? now() : null,
            'verified_purchase' => true,
        ]);

        if ($preVerified) {
            return;
        }

        // A mail transport failure must never turn into a 500 for the
        // visitor — the same "attempt and log, don't let it break the
        // primary action" convention SendOrderStatusEmails::send() uses.
        // The review row above is already saved either way; only the
        // notification is best-effort.
        try {
            Mail::to($data['email'])->send(new ReviewConfirmationMail($review));
        } catch (Throwable $exception) {
            Log::error('Could not send the review confirmation email.', [
                'review_id' => $review->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Idempotent — a second click on the same email link (or a page
     * refresh) just returns the already-confirmed review rather than
     * erroring.
     */
    public function confirm(Review $review): Review
    {
        if ($review->confirmed_at !== null) {
            return $review;
        }

        $review->update([
            'confirmed_at' => now(),
            'status' => ReviewStatus::Approved,
        ]);

        return $review;
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
    public function listForProduct(Product $product, string $sort, int $page, int $perPage = 4): LengthAwarePaginator
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
                // email/display_name cover guest reviews directly (no user
                // row to join through); the orWhereHas('user') branch still
                // covers the authenticated-flow reviews that predate them.
                $query->where('title', 'like', $term)
                    ->orWhere('body', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('display_name', 'like', $term)
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
