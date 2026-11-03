<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The kit bonus item described on the funnel page as "PDF Наръчник за
 * естествена грижа за устната кухина - ... изпраща се по имейл ... след
 * доставена поръчка" (see content/kitProducts.ts's KIT_BONUS_ITEMS).
 *
 * Not part of the automatic delivery flow - OrderDeliveredMail attaches
 * this same PDF directly for a kit order instead (by request, to keep a
 * delivered kit order down to two emails total rather than three; see
 * that Mailable's own docblock). This one is now only ever sent manually,
 * via OrderHandbookService::send() from the admin "Send kit handbook"
 * button - for the backlog of kit orders already delivered before this
 * feature existed, and as an ordinary standalone resend afterward.
 *
 * The PDF is a static file bundled with the backend (resources/documents/
 * kit-handbook.pdf), not the funnel FAQ's own attachment_url (same
 * document today, but that one is admin-replaceable storefront content
 * for an unrelated page - this fulfillment email should never silently
 * start attaching whatever an admin last uploaded to a different FAQ
 * answer).
 */
class OrderHandbookMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Твоят наръчник за естествена грижа за устната кухина',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.orders.handbook');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath(resource_path('documents/kit-handbook.pdf'))
                ->as('Наръчник за естествена грижа за устната кухина.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
