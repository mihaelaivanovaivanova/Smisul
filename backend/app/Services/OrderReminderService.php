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
 * Powers the daily orders:send-thirty-day-reminders command. Finds every
 * Delivered order that reached that status 30+ days ago and hasn't had its
 * reminder sent yet, emails each one, and marks it sent - once only, ever,
 * per order (thirty_day_reminder_sent_at is set on success and checked on
 * every run, so a later run never re-sends to the same order).
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
