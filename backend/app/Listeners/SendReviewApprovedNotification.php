<?php

namespace App\Listeners;

use App\Events\Review\ReviewApproved;
use App\Notifications\ReviewApprovedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Historically assumed every review had a user_id - broke the moment this
 * event started firing for guest reviews too (no User row to call
 * ->notify() on; see ReviewService::create()/confirm()/
 * submitForConfirmation(), none of which fired ReviewApproved until this
 * change). Routes on-demand to the reviewer's resolved email instead of a
 * Notifiable model for a guest, same mechanism Laravel's own "anonymous
 * notifiable" supports.
 *
 * Also now wrapped in try/catch - this event fires synchronously from
 * inside the request that just created/confirmed the review (ReviewService::
 * create()/confirm()/submitForConfirmation()), so an unwrapped mail
 * transport failure here would 500 that request even though the review
 * itself was already saved. Every other mail send in this app follows the
 * same "attempt and log, never let it break the primary action" rule (see
 * e.g. SendOrderStatusEmails::send()) - this one just hadn't needed it
 * before, back when ReviewApproved only fired from the rare manual admin
 * approve() action.
 */
class SendReviewApprovedNotification
{
    public function handle(ReviewApproved $event): void
    {
        $review = $event->review;
        $notification = new ReviewApprovedNotification($review);

        try {
            if ($review->user !== null) {
                $review->user->notify($notification);

                return;
            }

            $email = $review->reviewerEmail();

            if ($email !== null) {
                Notification::route('mail', $email)->notify($notification);
            }
        } catch (Throwable $exception) {
            Log::error('Could not send the review-approved notification.', [
                'review_id' => $review->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
