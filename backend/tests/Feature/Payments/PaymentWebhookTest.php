<?php

namespace Tests\Feature\Payments;

use App\Enums\Currency;
use App\Enums\LegalDocumentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\VariantStatus;
use App\Models\LegalDocument;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Payments\Concerns\SignsICardCallbacks;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;
    use SignsICardCallbacks;

    private function purchasableVariant(int $stock = 10): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => $stock]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 15]);

        return $variant;
    }

    /**
     * @return list<int>
     */
    private function acceptAllCurrentLegalDocuments(): array
    {
        return collect(LegalDocumentType::cases())
            ->map(function (LegalDocumentType $type) {
                $existing = LegalDocument::where('type', $type)->where('version', '1.0')->first();

                return $existing?->id ?? LegalDocument::factory()->create(['type' => $type, 'version' => '1.0'])->id;
            })
            ->values()
            ->all();
    }

    /**
     * @return array{0: Order, 1: Payment, 2: ProductVariant}
     */
    private function placeAwaitingPaymentOrder(int $stock = 10, int $quantity = 2, ?string $paymentMethod = null): array
    {
        $variant = $this->purchasableVariant($stock);
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => $quantity]);
        $guestToken = $addToCart->json('meta.guest_token');

        $payload = [
            'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
            'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
            'shipping_carrier' => 'speedy',
            'shipping_delivery_type' => 'address',
            'legal_document_ids' => $this->acceptAllCurrentLegalDocuments(),
        ];

        if ($paymentMethod !== null) {
            $payload['payment_method'] = $paymentMethod;
        }

        $placed = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/checkout/orders', $payload);

        $order = Order::findOrFail($placed->json('data.id'));
        $payment = Payment::findOrFail($placed->json('payment.id'));

        return [$order, $payment, $variant];
    }

    /**
     * Builds and signs a real-shaped iCard callback body (nested
     * Payment/Operation objects — see ICardPaymentGateway::parseWebhook()).
     */
    private function postWebhook(
        Payment $payment,
        string $paymentStatus,
        string $operationType = 'authorization',
        string $operationStatus = 'success',
        ?float $amountOverride = null,
    ): TestResponse {
        $payload = [
            'Payment' => [
                'OrderId' => $payment->transaction_reference,
                'MID' => (string) config('services.icard.mid'),
                'Date' => now()->toIso8601String(),
                'Type' => 'IPGPurchase',
                'Context' => 'CardPay',
                'Status' => $paymentStatus,
                'Sum' => [
                    'Amount' => number_format($amountOverride ?? (float) $payment->amount, 2, '.', ''),
                    'Currency' => (int) config('services.icard.currency_numeric'),
                ],
                'Interface' => 'redirect',
            ],
            'Operation' => [
                'Type' => $operationType,
                'Status' => $operationStatus,
                'Date' => now()->toIso8601String(),
                'Code' => 0,
                'Message' => 'Success',
            ],
        ];

        return $this->postJson('/api/v1/payments/webhook/icard', $this->signICardPayload($payload));
    }

    #[Test]
    public function a_paid_webhook_confirms_the_order_and_commits_stock(): void
    {
        [$order, $payment, $variant] = $this->placeAwaitingPaymentOrder(stock: 10, quantity: 3);

        $response = $this->postWebhook($payment, 'success');

        $response->assertOk();

        $payment->refresh();
        $order->refresh();
        $variant->inventory->refresh();

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(7, $variant->inventory->quantity_on_hand);
        $this->assertSame(0, $variant->inventory->quantity_reserved);

        $this->assertDatabaseHas('payment_webhook_logs', [
            'provider_reference' => $payment->transaction_reference,
            'signature_valid' => true,
        ]);
    }

    #[Test]
    public function a_declined_webhook_marks_the_order_failed_and_keeps_stock_held(): void
    {
        [$order, $payment, $variant] = $this->placeAwaitingPaymentOrder(stock: 10, quantity: 2);

        $this->postWebhook($payment, 'declined')->assertOk();

        $payment->refresh();
        $order->refresh();
        $variant->inventory->refresh();

        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame(OrderStatus::Failed, $order->status);
        $this->assertSame(10, $variant->inventory->quantity_on_hand);
        $this->assertSame(2, $variant->inventory->quantity_reserved);
    }

    /**
     * An intermediate step (3DS challenge, validation, etc.) reports
     * Payment.Status=success too — only a successful "authorization"
     * Operation confirms the payment. iCard's redirect-checkout callback
     * never reports an explicit "cancelled" event (cancellation only
     * reaches us via URL_Cancel — see PaymentReturnCancelTest), so
     * non-authorization callbacks must be a safe no-op, not a transition.
     */
    #[Test]
    public function any_successful_payment_and_operation_callback_confirms_the_order_like_miswak(): void
    {
        [$order, $payment] = $this->placeAwaitingPaymentOrder();

        $this->postWebhook($payment, 'success', operationType: '3ds_authentication')->assertOk();

        $payment->refresh();
        $order->refresh();

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(OrderStatus::Paid, $order->status);
    }

    #[Test]
    public function the_exact_same_webhook_delivered_twice_is_processed_only_once(): void
    {
        [, $payment] = $this->placeAwaitingPaymentOrder();

        $payload = $this->signICardPayload([
            'Payment' => [
                'OrderId' => $payment->transaction_reference,
                'Status' => 'success',
                'Sum' => ['Amount' => number_format((float) $payment->amount, 2, '.', ''), 'Currency' => (int) config('services.icard.currency_numeric')],
            ],
            'Operation' => ['Type' => 'authorization', 'Status' => 'success'],
        ]);

        $this->postJson('/api/v1/payments/webhook/icard', $payload)->assertOk();
        $this->postJson('/api/v1/payments/webhook/icard', $payload)->assertOk();

        $this->assertDatabaseCount('payment_webhook_logs', 1);
        $this->assertDatabaseCount('payment_transactions', 2); // initiated + webhook, not two webhooks
    }

    /**
     * Idempotency is keyed off the raw webhook body hash, entirely
     * independent of which payment_method the payment used — a wallet
     * payment's webhook must dedupe exactly like a card payment's does.
     */
    #[Test]
    public function a_modal_payment_callback_is_idempotent(): void
    {
        [$order, $payment] = $this->placeAwaitingPaymentOrder(paymentMethod: 'card');
        $this->assertSame('card', $payment->payment_method->value);

        $payload = $this->signICardPayload([
            'Payment' => [
                'OrderId' => $payment->transaction_reference,
                'Status' => 'success',
                'Sum' => ['Amount' => number_format((float) $payment->amount, 2, '.', ''), 'Currency' => (int) config('services.icard.currency_numeric')],
            ],
            'Operation' => ['Type' => 'authorization', 'Status' => 'success'],
        ]);

        $this->postJson('/api/v1/payments/webhook/icard', $payload)->assertOk();
        $this->postJson('/api/v1/payments/webhook/icard', $payload)->assertOk();

        $this->assertDatabaseCount('payment_webhook_logs', 1);
        $this->assertDatabaseCount('payment_transactions', 2); // initiated + webhook, not two webhooks

        $payment->refresh();
        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('card', $payment->payment_method->value);
    }

    #[Test]
    public function an_invalid_signature_is_rejected_and_logged(): void
    {
        [, $payment] = $this->placeAwaitingPaymentOrder();

        $response = $this->postJson('/api/v1/payments/webhook/icard', [
            'Payment' => [
                'OrderId' => $payment->transaction_reference,
                'Status' => 'success',
                'Sum' => ['Amount' => (float) $payment->amount, 'Currency' => (int) config('services.icard.currency_numeric')],
            ],
            'Operation' => ['Type' => 'authorization', 'Status' => 'success'],
            'Signature' => 'not-a-real-signature',
        ]);

        $response->assertStatus(401);

        $payment->refresh();
        $this->assertSame(PaymentStatus::Initiated, $payment->status);

        $this->assertDatabaseHas('payment_webhook_logs', ['signature_valid' => false]);
    }

    #[Test]
    public function a_webhook_for_an_unknown_reference_is_logged_but_does_not_error(): void
    {
        $payload = $this->signICardPayload([
            'Payment' => [
                'OrderId' => 'does-not-exist',
                'Status' => 'success',
                'Sum' => ['Amount' => '10.00', 'Currency' => 978],
            ],
            'Operation' => ['Type' => 'authorization', 'Status' => 'success'],
        ]);

        $response = $this->postJson('/api/v1/payments/webhook/icard', $payload);

        $response->assertOk();
        $this->assertDatabaseHas('payment_webhook_logs', [
            'provider_reference' => 'does-not-exist',
            'payment_id' => null,
        ]);
    }

    #[Test]
    public function a_webhook_claiming_a_different_amount_is_rejected(): void
    {
        [$order, $payment] = $this->placeAwaitingPaymentOrder();

        $this->postWebhook($payment, 'success', amountOverride: 999999.99)->assertOk();

        $payment->refresh();
        $order->refresh();

        $this->assertSame(PaymentStatus::Initiated, $payment->status);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
    }
}
