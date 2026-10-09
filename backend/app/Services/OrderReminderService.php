<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Mail\OrderThirtyDayReminderMail;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Powers the on-demand orders:send-thirty-day-reminders command and the
 * admin "Send reminder emails" button (Admin\OrderController::
 * sendReminderEmails()) - not scheduled, by request (see routes/
 * console.php). Finds every Delivered order that reached that status 30+
 * days ago and hasn't had its reminder sent yet, emails each one, and
 * marks it sent - once only, ever, per order (thirty_day_reminder_sent_at
 * is set on success and checked on every run, so pressing the button
 * repeatedly, or any day, never re-sends to the same order).
 */
class OrderReminderService
{
    private const REMINDER_AFTER_DAYS = 30;

    /**
     * One order can only ever reach "Delivered" once in this app's normal
     * flow (no Delivered -> X -> Delivered cycle exists in practice), so
     * the existence of a Delivered status-history row old enough is
     * equivalent to "became Delivered 30+ days ago" - no need to resolve
     * which row is the authoritative one.
     *
     * @return array{sent: int, failed: int}
     */
    public function sendDueReminders(): array
    {
        $sent = 0;
        $failed = 0;

        foreach ($this->dueOrders()->cursor() as $order) {
            if ($this->sendReminder($order)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * @return Builder<Order>
     */
    private function dueOrders(): Builder
    {
        return Order::query()
            ->where('status', OrderStatus::Delivered)
            ->whereNull('thirty_day_reminder_sent_at')
            ->whereNotNull('customer_email')
            ->whereHas('statusHistories', function ($query) {
                $query->where('status', OrderStatus::Delivered)
                    ->where('created_at', '<=', now()->subDays(self::REMINDER_AFTER_DAYS));
            });
    }

    /**
     * TEMPORARY test-only helper behind the admin "send-reminder-email-now"
     * route — lets an admin verify the real reminder mailable actually
     * renders and delivers in production, on demand, against any address,
     * without waiting 30 real days for a Delivered order to age into
     * eligibility. Deliberately bypasses every eligibility check
     * dueOrders() applies (age, customer_email, thirty_day_reminder_sent_at)
     * and never writes thirty_day_reminder_sent_at — this is not the real
     * reminder send, just a delivery check for whichever $order's content
     * the admin wants to preview. Remove this method and its route/
     * controller action once that's verified; see the commit introducing it.
     */
    public function sendTestReminder(Order $order, string $email): bool
    {
        try {
            Mail::to($email)->send(new OrderThirtyDayReminderMail($order));
        } catch (Throwable $exception) {
            Log::error('Could not send the test 30-day order reminder email.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'email' => $email,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Mirrors SendOrderStatusEmails::send()'s own resilience convention: a
     * mail transport failure is logged and skipped, never thrown - one bad
     * send must not stop the rest of the batch, and must not mark this
     * order as reminded (so it's retried on the next run instead of
     * silently never reminded).
     */
    private function sendReminder(Order $order): bool
    {
        try {
            Mail::to($order->customer_email)->send(new OrderThirtyDayReminderMail($order));
        } catch (Throwable $exception) {
            Log::error('Could not send the 30-day order reminder email.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        $order->update(['thirty_day_reminder_sent_at' => now()]);

        return true;
    }
}
