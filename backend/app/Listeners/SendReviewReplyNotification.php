<?php

namespace App\Listeners;

use App\Events\Review\ReviewReplied;
use App\Notifications\ReviewReplyNotification;

class SendReviewReplyNotification
{
    public function handle(ReviewReplied $event): void
    {
        $event->review->user->notify(new ReviewReplyNotification($event->review));
    }
}
