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
 *
 * Also powers sendTestReminderForTestAccount() below - the same send, on
 * demand for a single order, restricted to known test accounts (see its
 * own docblock).
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
     * Backs the "Send test reminder email" button that appears on an order's
     * admin detail page only when that order's customer_email is one of
     * OrderService::TEST_CUSTOMER_EMAILS (see OrderResource::is_test_account)
     * — lets an admin verify the real reminder mailable still renders and
     * delivers against production's real mail transport, on demand, without
     * waiting 30 real days for a Delivered order to age into eligibility,
     * and without touching any real customer's order. A fresh manually-
     * created order with a test-account email (see StoreManualOrderRequest's
     * own docblock) can be marched to Delivered and tested again any number
     * of times.
     *
     * Deliberately reuses sendReminder() below rather than duplicating it,
     * so this is a genuine dry run of the exact real send - same mailable,
     * same recipient resolution (order.customer_email, no override), same
     * thirty_day_reminder_sent_at bookkeeping. The only check it skips is
     * dueOrders()'s 30-day age requirement; every other real requirement
     * (Delivered status, has an email, not already reminded) still applies,
     * on top of the test-account gate.
     *
     * @return array{sent: bool, reason: string|null}
     */
    public function sendTestReminderForTestAccount(Order $order): array
    {
        if (! OrderService::isTestCustomerEmail($order->customer_email)) {
            return ['sent' => false, 'reason' => 'This order is not from a known test account.'];
        }

        if ($order->status !== OrderStatus::Delivered) {
            return ['sent' => false, 'reason' => 'Order must be Delivered first.'];
        }

        if ($order->thirty_day_reminder_sent_at !== null) {
            return ['sent' => false, 'reason' => 'A reminder was already sent for this order - create a new test order to try again.'];
        }

        $sent = $this->sendReminder($order);

        return ['sent' => $sent, 'reason' => $sent ? null : 'Sending failed - see the logs.'];
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
