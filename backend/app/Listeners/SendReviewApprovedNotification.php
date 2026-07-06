<?php

namespace App\Listeners;

use App\Events\Review\ReviewApproved;
use App\Notifications\ReviewApprovedNotification;

class SendReviewApprovedNotification
{
    public function handle(ReviewApproved $event): void
    {
        $event->review->user->notify(new ReviewApprovedNotification($event->review));
    }
}
