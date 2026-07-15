<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once, when a funnel lead's email is first captured — delivers the
 * promised value (the usage manual PDF) immediately, so the first touch
 * is a gift rather than a marketing blast.
 */
class FunnelLeadWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Благодарим ти! Ето и нашето ръководство за Miswak',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.funnel-lead-welcome', with: [
            'manualUrl' => rtrim(config('app.frontend_url'), '/').'/funnel/docs/miswak-usage-manual.pdf',
            'shopUrl' => config('app.frontend_url'),
        ]);
    }
}
