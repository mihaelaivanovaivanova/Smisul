<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

/**
 * Sent by ShipmentPickupReminderService when a shipment reaches
 * ShipmentStatus::ReadyForPickup (the parcel is sitting at a Speedy
 * office/APT or BOX NOW locker, waiting for the customer to collect it) -
 * by request, since a recurring customer complaint was that Speedy's own
 * pickup notification doesn't reliably reach them. One class, three
 * escalating stages rather than three separate Mailables, since they share
 * everything but tone/subject - same reuse pattern as
 * OrderThirtyDayReminderMail being one class for one recurring kind of
 * nudge. Transactional, not promotional (no HasUnsubscribeLink) - this is
 * operational information about where the customer's own paid-for parcel
 * physically is, not something to opt out of, same as OrderDeliveredMail.
 */
class ShipmentReadyForPickupMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  int  $stage  1 = immediate, 2 = +3 days unpicked, 3 = +6 days unpicked (final)
     */
    public function __construct(
        public readonly Order $order,
        public readonly Shipment $shipment,
        public readonly int $stage,
    ) {
        if (! in_array($stage, [1, 2, 3], true)) {
            throw new InvalidArgumentException("Invalid pickup reminder stage [{$stage}] - must be 1, 2, or 3.");
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    /** $order/$shipment/$stage are all public readonly constructor properties - Mailable::buildViewData() exposes them to the view automatically, no `with` needed. */
    public function content(): Content
    {
        return new Content(view: 'emails.orders.pickup-reminder');
    }

    /** Named subjectLine(), not subject() - Mailable already declares a public fluent subject() setter that a same-named private method here would collide with. */
    private function subjectLine(): string
    {
        return match ($this->stage) {
            1 => "Пратка {$this->order->order_number} те очаква за получаване",
            2 => "Напомняне: пратка {$this->order->order_number} все още те чака",
            3 => "Последно напомняне: пратка {$this->order->order_number}",
            // Unreachable - the constructor above already rejects any
            // other value - kept so this match stays exhaustive for
            // PHPStan rather than claiming $stage is always 1|2|3.
            default => throw new InvalidArgumentException("Invalid pickup reminder stage [{$this->stage}]."),
        };
    }
}
