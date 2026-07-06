<?php

namespace App\Policies;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\User;

/**
 * Purchase/delivery/duplicate eligibility is NOT checked here — that's
 * ReviewService::assertEligible()'s job, thrown as ReviewNotEligibleException
 * (422) rather than a policy 403, since it's a business-rule rejection, not
 * an authorization one. This policy only covers "is this the right kind of
 * user, touching their own review, at the right moment in its lifecycle".
 */
class ReviewPolicy
{
    /**
     * Gates both "create a review" and "list my own reviews" — an
     * administrator reviewing as a customer is explicitly disallowed by
     * the sprint brief.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCustomer();
    }

    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    /**
     * Reviews publish immediately (see ReviewService::create) — there is no
     * pending window to restrict editing to, so ownership is the only
     * check. Editing doesn't reset an admin-hidden/rejected review back to
     * approved; it just updates the content in place.
     */
    public function update(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    /**
     * Anyone authenticated may vote a review helpful — not customer-only,
     * since browsing/voting isn't the same trust boundary as authoring a
     * verified-purchase review. Voting for your own review doesn't count.
     */
    public function markHelpful(User $user, Review $review): bool
    {
        return $review->status === ReviewStatus::Approved && $user->id !== $review->user_id;
    }
}
