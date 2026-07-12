<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentOperationsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_admin_can_reverse_a_paid_payment_using_the_callback_trn(): void
    {
        $admin = User::factory()->administrator()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);
        $payment = Payment::factory()->paid()->for($order)->create(['gateway_transaction_reference' => '202601010000001']);
        Http::fake(['*' => Http::response(['Status' => '0', 'IPGTrnref' => 'reverse-trn'])]);

        $this->actingAs($admin)->postJson("/api/v1/admin/payments/{$payment->id}/reverse")->assertOk();

        Http::assertSent(function ($request) use ($payment) {
            parse_str($request->body(), $fields);

            // OrderID must be a fresh value, distinct from the payment's own
            // transaction_reference — iCard rejects a resubmission of an
            // OrderID it already saw for the original purchase call with
            // "9019 Duplicated transaction".
            return ($fields['IPGmethod'] ?? null) === 'IPGReversal'
                && ($fields['IPG_Trnref'] ?? null) === '202601010000001'
                && filled($fields['OrderID'] ?? null)
                && ($fields['OrderID'] ?? null) !== $payment->transaction_reference;
        });
        $this->assertSame(PaymentStatus::Cancelled, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    #[Test]
    public function a_full_refund_marks_payment_and_order_refunded(): void
    {
        $admin = User::factory()->administrator()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Paid, 'grand_total' => 20]);
        $payment = Payment::factory()->paid()->for($order)->create([
            'amount' => 20, 'gateway_transaction_reference' => '202601010000002',
        ]);
        Http::fake(['*' => Http::response(['status' => '0', 'trnref' => 'refund-trn'])]);

        $this->actingAs($admin)->postJson("/api/v1/admin/payments/{$payment->id}/refund", ['amount' => 20])->assertOk();

        Http::assertSent(function ($request) use ($payment) {
            parse_str($request->body(), $fields);

            return ($fields['IPGmethod'] ?? null) === 'IPGRefund'
                && ($fields['Amount'] ?? null) === '20.00'
                && filled($fields['OrderID'] ?? null)
                && ($fields['OrderID'] ?? null) !== $payment->transaction_reference;
        });
        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
    }
}
