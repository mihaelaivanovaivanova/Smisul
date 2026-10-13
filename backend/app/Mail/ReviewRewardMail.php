<?php

namespace App\Mail;

use App\Models\DiscountCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once, by IssueReviewRewardCode, the moment a review becomes
 * approved. A direct consequence of something the customer just did (not
 * an unsolicited promo), so unlike BroadcastMail/the 30-day reminder this
 * carries no List-Unsubscribe header - it's treated as transactional, the
 * same classification as OrderConfirmationMail.
 */
class ReviewRewardMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly DiscountCode $discountCode,
        public readonly string $firstName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Твоята -10% отстъпка е готова 🎁',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reviews.reward', with: [
            'firstName' => $this->firstName,
            'code' => $this->discountCode->code,
            'discountPercent' => (float) $this->discountCode->discount_percent,
            'expiresAt' => $this->discountCode->expires_at,
            'shopUrl' => rtrim(config('app.frontend_url'), '/'),
        ]);
    }
}
