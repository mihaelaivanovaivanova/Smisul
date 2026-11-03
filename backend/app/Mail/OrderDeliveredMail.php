<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\OrderHandbookService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * For a kit order, this single email now also carries the kit's bonus
 * handbook PDF (and a short paragraph about it in the body) instead of a
 * separate follow-up email - by request, to keep a delivered kit order
 * down to two emails total (this one + OrderInvoiceMail) rather than
 * three. See OrderHandbookService::orderHasKit() for the shared check,
 * and that service's own docblock for the one case a *separate* handbook
 * email still exists: the admin "Send kit handbook" resend button, for
 * orders already delivered before this merge existed.
 *
 * $hasKit is computed once in the constructor (not re-derived in
 * content()/attachments()) so SendOrderStatusEmails can read it straight
 * off the Mailable after sending, to decide whether to also stamp
 * handbook_email_sent_at - without either side recomputing the same
 * check twice or risking the two disagreeing.
 */
class OrderDeliveredMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly bool $hasKit;

    public function __construct(public readonly Order $order)
    {
        $order->loadMissing('items');
        $this->hasKit = OrderHandbookService::orderHasKit($order);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Поръчка {$this->order->order_number} е доставена",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.orders.delivered');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if (! $this->hasKit) {
            return [];
        }

        return [
            Attachment::fromPath(resource_path('documents/kit-handbook.pdf'))
                ->as('Наръчник за естествена грижа за устната кухина.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
