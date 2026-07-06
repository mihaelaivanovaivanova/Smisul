<?php

namespace App\Events\Review;

use App\Models\Review;
use Illuminate\Foundation\Events\Dispatchable;

class ReviewReplied
{
    use Dispatchable;

    public function __construct(
        public readonly Review $review,
    ) {}
}
