<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\StoredPaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Payments\Concerns\SignsICardCallbacks;
use Tests\TestCase;

class StoredCardCallbackTest extends TestCase
{
    use RefreshDatabase;
    use SignsICardCallbacks;

    #[Test]
    public function a_signed_store_card_callback_saves_only_the_tokenized_card_for_the_customer(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->forUser($user)->create(['status' => OrderStatus::AwaitingPayment, 'grand_total' => 20]);
        $payment = Payment::factory()->initiated()->for($order)->create(['amount' => 20]);
        $token = str_repeat('A', 64);
        $payload = $this->signICardPayload([
            'CardData' => ['Pan' => '532610***0004', 'Type' => 'MasterCard', 'ExpMonth' => '06', 'ExpYear' => '29'],
            'Payment' => [
                'OrderId' => $payment->provider_reference, 'Status' => 'success',
                'Sum' => ['Amount' => '20.00', 'Currency' => 978],
            ],
            'Operation' => [
                'Type' => 'authorization', 'Status' => 'success',
                'Provider' => ['Trn' => '202601010000003'],
                'StoreCard' => ['CardToken' => $token],
            ],
        ]);

        $this->postJson('/api/v1/payments/webhook/icard', $payload)->assertOk();

        $stored = StoredPaymentMethod::firstOrFail();
        $this->assertSame($user->id, $stored->user_id);
        $this->assertSame('0004', $stored->last_four);
        $this->assertSame($token, $stored->card_token);
        $this->assertSame('202601010000003', $payment->fresh()->gateway_transaction_reference);
        $this->assertSame('[REDACTED]', data_get(PaymentWebhookLog::firstOrFail()->payload, 'Operation.StoreCard.CardToken'));
    }
}
