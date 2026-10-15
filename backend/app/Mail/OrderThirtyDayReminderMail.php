<?php

namespace App\Mail;

use App\Mail\Concerns\HasUnsubscribeLink;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Sent once per order by the daily OrderReminderService job, 30+ days after
 * the order was marked Delivered (see thirty_day_reminder_sent_at on the
 * orders table) - a check-in ("how's the stick going?") plus a nudge
 * toward two new accessory products and a product review, not a
 * transactional/legal notice, so it skips the usual legal-disclosures
 * partial other order emails include.
 */
class OrderThirtyDayReminderMail extends Mailable
{
    use HasUnsubscribeLink, Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Мина месец… И ще оценим твоят коментар',
        );
    }

    public function headers(): Headers
    {
        return $this->unsubscribeHeaders($this->order->customer_email);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.orders.thirty-day-reminder', with: [
            'reviewUrl' => $this->reviewUrl(),
            'unsubscribeUrl' => $this->unsubscribeUrl($this->order->customer_email),
        ]);
    }

    /**
     * Same signed-backend-URL-rehosted-on-the-frontend pattern as
     * ReviewConfirmationMail::confirmationUrl() - a real signed route
     * purely to obtain a valid expires+signature pair, then re-hosted on
     * the frontend product page so the review wizard can call
     * orders.review-identity directly and skip asking for an email/display
     * name it can already resolve. 30 days (not reviews.confirm's 48
     * hours) since this link's whole purpose is being clicked well after
     * the fact - there's no "did you mean to request this" freshness
     * concern the way a confirmation link has.
     *
     * Links to the review page of the product actually on this order, not
     * a hardcoded Miswak - this store sells two other products (the case,
     * the tongue scraper) and an order for either of those was previously
     * always sent to /products/miswak, where ReviewService::
     * findEligibleOrderForEmail() would never find a Delivered Miswak
     * order for that email and silently no-op (same generic "thanks" the
     * wizard shows either way - see AddReviewWizard's done step). Falls
     * back to 'miswak' only if the order's variant/product was deleted
     * since (product_variant_id is nullOnDelete).
     */
    private function reviewUrl(): string
    {
        $signedBackendUrl = URL::temporarySignedRoute(
            'orders.review-identity',
            now()->addDays(30),
            ['order' => $this->order->id],
        );

        $query = parse_url($signedBackendUrl, PHP_URL_QUERY);
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $productSlug = $this->order->items->first()?->productVariant?->product->slug ?? 'miswak';

        return "{$frontendUrl}/products/{$productSlug}?write_review=1&order_id={$this->order->id}&{$query}";
    }
}
