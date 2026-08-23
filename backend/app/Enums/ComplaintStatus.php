<?php

namespace App\Enums;

enum ComplaintStatus: string
{
    case Received = 'received';
    case InReview = 'in_review';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::InReview => 'In Review',
            self::Resolved => 'Resolved',
            self::Rejected => 'Rejected',
        };
    }

    /** Terminal states - the moment resolved_at gets stamped. */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Resolved, self::Rejected => true,
            self::Received, self::InReview => false,
        };
    }
}
