<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent manually by an admin (the "Send pickup notification" button on a
 * Shipped Speedy order - see ShipmentPickupNotificationService) - by
 * request, after a recurring customer complaint that Speedy's own pickup
 * notification doesn't reliably reach them. Deliberately a single generic
 * email with no dates or urgency language, triggered on demand rather than
 * on any automatic schedule - an earlier escalating three-stage design
 * (immediate/+3d/+6d) was built and then scrapped by request before ever
 * going live, in favour of this simpler manual trigger. Transactional, not
 * promotional (no HasUnsubscribeLink) - this is operational information
 * about where the customer's own paid-for parcel physically is, not
 * something to opt out of, same as OrderDeliveredMail.
 */
class ShipmentPickupNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly Shipment $shipment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Пратка {$this->order->order_number} те очаква за получаване");
    }

    /** $order/$shipment are public readonly constructor properties - Mailable::buildViewData() exposes them to the view automatically, no `with` needed. */
    public function content(): Content
    {
        return new Content(view: 'emails.orders.pickup-notification');
    }
}
