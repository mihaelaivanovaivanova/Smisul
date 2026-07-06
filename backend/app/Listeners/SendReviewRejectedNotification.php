<?php

namespace App\Listeners;

use App\Events\Review\ReviewRejected;
use App\Notifications\ReviewRejectedNotification;

class SendReviewRejectedNotification
{
    public function handle(ReviewRejected $event): void
    {
        $event->review->user->notify(new ReviewRejectedNotification($event->review, $event->reason));
    }
}
