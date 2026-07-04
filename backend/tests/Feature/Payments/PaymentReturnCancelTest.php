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
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Payments\Concerns\SignsICardCallbacks;
use Tests\TestCase;

class PaymentReturnCancelTest extends TestCase
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
    private function placeAwaitingPaymentOrder(int $stock = 10, int $quantity = 2): array
    {
        $variant = $this->purchasableVariant($stock);
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => $quantity]);
        $guestToken = $addToCart->json('meta.guest_token');

        $placed = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/checkout/orders', [
            'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
            'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
            'shipping_carrier' => 'econt',
            'legal_document_ids' => $this->acceptAllCurrentLegalDocuments(),
        ]);

        $order = Order::findOrFail($placed->json('data.id'));
        $payment = Payment::findOrFail($placed->json('payment.id'));

        return [$order, $payment, $variant];
    }

    /**
     * iCard's redirect-checkout API documents no server-to-server
     * status-inquiry call, so reconcile() has nothing to actively check —
     * visiting the return page before the webhook arrives is a best-effort
     * no-op that logs the visit and otherwise leaves the payment as-is; the
     * webhook remains the authoritative source of truth.
     */
    #[Test]
    public function the_return_endpoint_leaves_the_payment_as_is_when_no_webhook_has_arrived_yet(): void
    {
        [$order, $payment] = $this->placeAwaitingPaymentOrder();

        $guestToken = $order->fresh()->guest_access_token;

        $response = $this->postJson("/api/v1/payments/{$order->id}/return?token={$guestToken}");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'initiated');

        $payment->refresh();
        $order->refresh();
        $this->assertSame(PaymentStatus::Initiated, $payment->status);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);

        $this->assertDatabaseHas('payment_transactions', ['payment_id' => $payment->id, 'type' => 'return']);
        $this->assertDatabaseMissing('payment_transactions', ['payment_id' => $payment->id, 'type' => 'status_check']);
    }

    #[Test]
    public function the_return_endpoint_does_nothing_once_a_webhook_already_settled_the_payment(): void
    {
        [$order, $payment] = $this->placeAwaitingPaymentOrder();

        $payload = $this->signICardPayload([
            'Payment' => [
                'OrderId' => $payment->transaction_reference,
                'Status' => 'success',
                'Sum' => ['Amount' => number_format((float) $payment->amount, 2, '.', ''), 'Currency' => (int) config('services.icard.currency_numeric')],
            ],
            'Operation' => ['Type' => 'authorization', 'Status' => 'success'],
        ]);
        $this->postJson('/api/v1/payments/webhook/icard', $payload)->assertOk();

        $guestToken = $order->fresh()->guest_access_token;
        $this->postJson("/api/v1/payments/{$order->id}/return?token={$guestToken}")->assertOk();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
    }

    #[Test]
    public function a_customer_can_cancel_their_own_pending_payment(): void
    {
        [$order, , $variant] = $this->placeAwaitingPaymentOrder(stock: 10, quantity: 3);

        $guestToken = $order->fresh()->guest_access_token;
        $response = $this->postJson("/api/v1/payments/{$order->id}/cancel?token={$guestToken}");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'cancelled');

        $order->refresh();
        $variant->inventory->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(10, $variant->inventory->quantity_on_hand);
        $this->assertSame(0, $variant->inventory->quantity_reserved);
    }
}
