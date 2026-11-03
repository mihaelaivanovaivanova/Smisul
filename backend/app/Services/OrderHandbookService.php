<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Mail\OrderHandbookMail;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Handbook content is now included directly in OrderDeliveredMail for
 * every kit order going forward (see that Mailable's own docblock) - this
 * service/mailable pair is no longer part of the automatic send, and has
 * no admin UI/HTTP endpoint of its own (that "Send kit handbook" button
 * was removed by request once the merge made it redundant for new
 * orders). orderHasKit() below stays load-bearing - OrderDeliveredMail
 * calls it directly. isEligible()/send() are kept deliberately unreachable-
 * but-available: a kit order delivered before the merge existed never got
 * a handbook email, and this is the tool to send one, invoked directly
 * (tinker/artisan) rather than through a UI - see the test file for the
 * exact call shape.
 */
class OrderHandbookService
{
    /**
     * True when at least one line on the order is a kit (bundle_id set) -
     * shared with OrderDeliveredMail's own decision to attach the PDF
     * there, so the two never check this differently. Relies on items()
     * already being eager-loaded wherever this is called from (see
     * OrderService::EAGER_LOAD) - a plain relation access, not a fresh
     * query, so it never N+1s across an order list.
     */
    public static function orderHasKit(Order $order): bool
    {
        return $order->items->contains(fn (OrderItem $item) => $item->isBundle());
    }

    public static function isEligible(Order $order): bool
    {
        return $order->status === OrderStatus::Delivered
            && $order->customer_email !== null
            && self::orderHasKit($order);
    }

    /**
     * @return array{sent: bool, reason: string|null}
     */
    public function send(Order $order): array
    {
        if (! self::isEligible($order)) {
            return ['sent' => false, 'reason' => 'This order does not currently qualify for the kit handbook email.'];
        }

        try {
            Mail::to($order->customer_email)->send(new OrderHandbookMail($order));
        } catch (Throwable $exception) {
            Log::error('Could not send the kit handbook email.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return ['sent' => false, 'reason' => 'Sending failed - see the logs.'];
        }

        $order->update(['handbook_email_sent_at' => now()]);

        return ['sent' => true, 'reason' => null];
    }
}
