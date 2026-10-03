<?php

namespace Tests\Feature\Checkout;

use App\Enums\OrderStatus;
use App\Mail\OrderThirtyDayReminderMail;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\OrderReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderThirtyDayReminderTest extends TestCase
{
    use RefreshDatabase;

    private function deliveredOrder(array $overrides = [], ?Carbon $deliveredAt = null): Order
    {
        $order = Order::factory()->create(array_merge(['status' => OrderStatus::Delivered], $overrides));

        OrderStatusHistory::factory()->create([
            'order_id' => $order->id,
            'status' => OrderStatus::Delivered,
            'previous_status' => OrderStatus::Shipped,
            'created_at' => $deliveredAt ?? now()->subDays(31),
        ]);

        return $order->fresh();
    }

    #[Test]
    public function an_order_delivered_31_days_ago_gets_a_reminder(): void
    {
        Mail::fake();

        $order = $this->deliveredOrder(['customer_email' => 'ivan@example.com']);

        $result = app(OrderReminderService::class)->sendDueReminders();

        $this->assertSame(['sent' => 1, 'failed' => 0], $result);
        Mail::assertSent(OrderThirtyDayReminderMail::class, fn (OrderThirtyDayReminderMail $mail) => $mail->hasTo('ivan@example.com'));
        $this->assertNotNull($order->fresh()->thirty_day_reminder_sent_at);
    }

    #[Test]
    public function an_order_delivered_less_than_30_days_ago_is_not_reminded_yet(): void
    {
        Mail::fake();

        $this->deliveredOrder(['customer_email' => 'ivan@example.com'], now()->subDays(10));

        $result = app(OrderReminderService::class)->sendDueReminders();

        $this->assertSame(['sent' => 0, 'failed' => 0], $result);
        Mail::assertNothingSent();
    }

    #[Test]
    public function an_order_already_reminded_is_not_reminded_again(): void
    {
        Mail::fake();

        $order = $this->deliveredOrder(['customer_email' => 'ivan@example.com', 'thirty_day_reminder_sent_at' => now()->subDay()]);

        $result = app(OrderReminderService::class)->sendDueReminders();

        $this->assertSame(['sent' => 0, 'failed' => 0], $result);
        Mail::assertNothingSent();
        $this->assertTrue($order->thirty_day_reminder_sent_at->equalTo($order->fresh()->thirty_day_reminder_sent_at));
    }

    #[Test]
    public function a_non_delivered_order_is_never_reminded_regardless_of_age(): void
    {
        Mail::fake();

        Order::factory()->create([
            'status' => OrderStatus::Shipped,
            'customer_email' => 'ivan@example.com',
            'created_at' => now()->subDays(60),
        ]);

        $result = app(OrderReminderService::class)->sendDueReminders();

        $this->assertSame(['sent' => 0, 'failed' => 0], $result);
        Mail::assertNothingSent();
    }

    #[Test]
    public function a_guest_order_is_reminded_too(): void
    {
        Mail::fake();

        $this->deliveredOrder(['user_id' => null, 'customer_email' => 'guest@example.com']);

        $result = app(OrderReminderService::class)->sendDueReminders();

        $this->assertSame(['sent' => 1, 'failed' => 0], $result);
        Mail::assertSent(OrderThirtyDayReminderMail::class, fn (OrderThirtyDayReminderMail $mail) => $mail->hasTo('guest@example.com'));
    }

    #[Test]
    public function a_mail_transport_failure_leaves_the_order_eligible_for_retry(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

        $order = $this->deliveredOrder(['customer_email' => 'ivan@example.com']);

        $result = app(OrderReminderService::class)->sendDueReminders();

        $this->assertSame(['sent' => 0, 'failed' => 1], $result);
        $this->assertNull($order->fresh()->thirty_day_reminder_sent_at);
    }

    #[Test]
    public function the_console_command_runs_successfully(): void
    {
        Mail::fake();

        $this->deliveredOrder(['customer_email' => 'ivan@example.com']);

        $this->artisan('orders:send-thirty-day-reminders')->assertExitCode(0);

        Mail::assertSent(OrderThirtyDayReminderMail::class);
    }

    /**
     * End-to-end: the "Остави ревю" link's signed query params (see
     * OrderThirtyDayReminderMail::reviewUrl()) must be real and accepted by
     * orders.review-identity (see OrderReviewIdentityTest for that
     * endpoint's own dedicated coverage) - not just well-formed looking.
     */
    #[Test]
    public function the_reviewUrl_link_is_a_real_signed_link_the_review_identity_endpoint_accepts(): void
    {
        $order = $this->deliveredOrder(['customer_email' => 'ivan@example.com', 'customer_first_name' => 'Ivan']);

        $mail = new OrderThirtyDayReminderMail($order);
        $rendered = $mail->render();

        // &amp; not & - Blade's {{ }} HTML-escapes the href attribute, same
        // as every other link in this email.
        $this->assertMatchesRegularExpression(
            '#/products/miswak\?write_review=1&amp;order_id='.$order->id.'&amp;expires=\d+&amp;signature=[a-f0-9]{64}#',
            $rendered,
        );

        // Only expires+signature - matches exactly what fetchOrderReviewIdentity()
        // actually sends on the frontend (ProductPage.tsx), not every
        // frontend-only query param the full href also carries
        // (write_review, a duplicate order_id) - those were never part of
        // what temporarySignedRoute() signed, so including them here would
        // (correctly) fail signature validation, same as a real tampered link.
        preg_match('/expires=(\d+)/', $rendered, $expiresMatch);
        preg_match('/signature=([a-f0-9]{64})/', $rendered, $signatureMatch);
        $response = $this->getJson("/api/v1/orders/{$order->id}/review-identity?expires={$expiresMatch[1]}&signature={$signatureMatch[1]}");

        $response->assertOk();
        $response->assertJsonPath('data.email', 'ivan@example.com');
        $response->assertJsonPath('data.display_name', 'Ivan');
    }
}
