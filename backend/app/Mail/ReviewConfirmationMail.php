<?php

namespace App\Mail;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Sent by the guest "Add a review" wizard — a Mailable (not a Notification)
 * specifically so its view can extend the same emails.layout every other
 * Smisul email uses (logo header, forest-green divider, cream card, footer
 * band): that layout relies on $message->embed() for the logo, which only a
 * Mailable's Content/view rendering provides, not a Notification's
 * MailMessage builder.
 */
class ReviewConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Review $review,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Потвърди отзива си за {$this->review->product->name}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reviews.confirmation', with: [
            'product' => $this->review->product,
            'confirmUrl' => $this->confirmationUrl(),
        ]);
    }

    /**
     * Same signed-backend-URL-rehosted-on-the-frontend pattern as email
     * verification (see AppServiceProvider::configureAuthNotificationUrls())
     * — generates a real signed route purely to obtain a valid
     * expires+signature pair, then re-hosts those same query params on the
     * frontend SPA so the visitor never leaves it; the frontend page reads
     * them and calls the backend confirmation endpoint directly.
     */
    private function confirmationUrl(): string
    {
        $signedBackendUrl = URL::temporarySignedRoute(
            'reviews.confirm',
            now()->addHours(48),
            ['review' => $this->review->id],
        );

        $query = parse_url($signedBackendUrl, PHP_URL_QUERY);

        return sprintf(
            '%s/reviews/confirm/%d?%s',
            rtrim(config('app.frontend_url'), '/'),
            $this->review->id,
            $query,
        );
    }
}
