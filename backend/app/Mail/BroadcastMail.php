<?php

namespace App\Mail;

use App\Mail\Concerns\HasUnsubscribeLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * The generic mailable behind the admin "Send broadcast" button (see
 * Admin\BroadcastController/BroadcastService) - one subject/body/optional
 * link, reused for whatever this week's promo happens to be, rather than a
 * new Mailable+view per campaign. Shares emails.layout with every
 * transactional mail (same header/footer/fonts) rather than one-off inline
 * styling, so a promo lands looking like it's really from this store, not
 * like a copy-pasted blast - a real (if soft) trust/deliverability signal
 * on top of just looking better.
 *
 * Sent from config('mail.broadcast_from') - a different sender identity
 * than order confirmations/the 30-day reminder (config('mail.from')), by
 * request, so this mailable's bulk/promotional sending pattern doesn't
 * drag Gmail's per-sender Promotions-tab reputation down for the other,
 * more personal mail sharing the same address would otherwise get.
 */
class BroadcastMail extends Mailable
{
    use HasUnsubscribeLink, Queueable, SerializesModels;

    /**
     * @param  list<string>  $paragraphs  Plain text, already split on blank lines - see BroadcastService::paragraphs().
     */
    public function __construct(
        public readonly string $recipientEmail,
        public readonly string $subjectLine,
        public readonly array $paragraphs,
        public readonly ?string $ctaUrl = null,
        public readonly ?string $ctaLabel = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.broadcast_from.address'), config('mail.broadcast_from.name')),
            subject: $this->subjectLine,
        );
    }

    public function headers(): Headers
    {
        return $this->unsubscribeHeaders($this->recipientEmail);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.broadcast', with: [
            'subjectLine' => $this->subjectLine,
            'paragraphs' => $this->paragraphs,
            'ctaUrl' => $this->ctaUrl,
            'ctaLabel' => $this->ctaLabel,
            'unsubscribeUrl' => $this->unsubscribeUrl($this->recipientEmail),
        ]);
    }
}
