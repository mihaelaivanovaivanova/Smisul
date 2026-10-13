<?php

namespace App\Listeners;

use App\Events\Review\ReviewApproved;
use App\Mail\ReviewRewardMail;
use App\Models\DiscountCode;
use App\Services\DiscountCodeService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The "-10% за следващата поръчка" thank-you for leaving a review - one
 * single-use, 30-day code per approved review, emailed to whoever wrote it.
 * Separate from SendReviewApprovedNotification (one listener per concern,
 * same convention as the OrderStatusChanged listeners) so a reward-mail
 * failure can never suppress the "your review is live" notification or vice
 * versa.
 */
class IssueReviewRewardCode
{
    public function __construct(private readonly DiscountCodeService $discountCodes) {}

    public function handle(ReviewApproved $event): void
    {
        $review = $event->review;

        // Idempotency: a unique index on discount_codes.review_id backs
        // this at the DB level too, but checking first avoids a pointless
        // "already have a code" DB error and - more importantly - avoids
        // re-emailing the customer a code they already received, in case
        // this event is ever dispatched twice for the same review.
        if (DiscountCode::query()->where('review_id', $review->id)->exists()) {
            return;
        }

        $email = $review->reviewerEmail();

        if ($email === null) {
            return;
        }

        try {
            $discountCode = DiscountCode::create([
                'code' => $this->discountCodes->generateUniqueCode(),
                'customer_email' => $email,
                'discount_percent' => 10,
                'max_uses' => 1,
                'expires_at' => now()->addDays(30),
                'review_id' => $review->id,
            ]);

            Mail::to($email)->send(new ReviewRewardMail($discountCode, $review->reviewerFirstName()));
        } catch (Throwable $exception) {
            Log::error('Could not issue or send the review reward discount code.', [
                'review_id' => $review->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
