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
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentInitiationTest extends TestCase
{
    use RefreshDatabase;

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

    private function placeOrder(): TestResponse
    {
        $variant = $this->purchasableVariant();
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $addToCart->json('meta.guest_token');

        return $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/checkout/orders', [
            'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
            'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
            'shipping_carrier' => 'speedy',
            'shipping_delivery_type' => 'address',
            'legal_document_ids' => $this->acceptAllCurrentLegalDocuments(),
        ]);
    }

    #[Test]
    public function placing_an_order_automatically_initiates_a_payment_session(): void
    {
        // Uses TestCase::setUp()'s default iCard fake (a real IPGPaymentToken
        // response with a random token) rather than a custom one — this
        // test cares about the request shape and the response being wired
        // through correctly, not a specific token value.
        $response = $this->placeOrder();

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'awaiting_payment');
        $response->assertJsonPath('payment.status', 'initiated');
        $response->assertJsonPath('payment.provider', 'icard');
        $this->assertNotEmpty($response->json('payment.modal_session.token'));
        $this->assertNotNull($response->json('payment.modal_session.modal_js_url'));

        Http::assertSent(function ($request) {
            // BannerIndex is required by IPG 4.5 for IPGPaymentToken (see
            // the "IPG API BM ECommerce" integration guide) — every
            // documented example uses "1", and there's no per-merchant
            // banner selection in this app, so it's a fixed value.
            return $request['IPGmethod'] === 'IPGPaymentToken'
                && $request['ModalType'] === 'IPGPurchase'
                && $request['BannerIndex'] === '1'
                && ! empty($request['Signature']);
        });

        $this->assertDatabaseHas('payments', [
            'order_id' => $response->json('data.id'),
            'status' => PaymentStatus::Initiated->value,
        ]);
        $this->assertDatabaseHas('payment_transactions', [
            'type' => 'initiated',
            'status' => PaymentStatus::Initiated->value,
        ]);
    }

    /**
     * Re-initiating (e.g. the "retry payment" flow after Failed/Cancelled)
     * must mint a fresh attempt with its own transaction_reference — iCard
     * rejects a resubmission of the same MID+OrderID it already saw, so
     * reusing the prior payment record here would break every retry.
     */
    #[Test]
    public function re_initiating_an_order_mints_a_fresh_payment_attempt(): void
    {
        $placed = $this->placeOrder();
        $orderId = $placed->json('data.id');
        $token = $placed->json('meta.guest_access_token');
        $firstPaymentId = $placed->json('payment.id');
        $firstReference = Payment::find($firstPaymentId)->transaction_reference;

        $response = $this->postJson("/api/v1/payments/{$orderId}/initiate?token={$token}");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'initiated');
        $this->assertNotSame($firstPaymentId, $response->json('data.id'));
        $this->assertNotSame($firstReference, Payment::find($response->json('data.id'))->transaction_reference);
        $this->assertDatabaseCount('payments', 2);
    }

    #[Test]
    public function a_guest_cannot_initiate_payment_for_an_order_that_isnt_theirs(): void
    {
        $placed = $this->placeOrder();
        $orderId = $placed->json('data.id');

        $this->postJson("/api/v1/payments/{$orderId}/initiate")->assertForbidden();
        $this->postJson("/api/v1/payments/{$orderId}/initiate?token=wrong-token")->assertForbidden();
    }

    #[Test]
    public function the_order_moves_to_awaiting_payment_exactly_once(): void
    {
        $placed = $this->placeOrder();
        $order = Order::findOrFail($placed->json('data.id'));

        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        $this->assertDatabaseCount('order_status_histories', 2); // pending, then awaiting_payment
    }

    #[Test]
    public function each_payment_gets_a_unique_transaction_reference(): void
    {
        $first = $this->placeOrder();
        $second = $this->placeOrder();

        $firstPayment = Payment::find($first->json('payment.id'));
        $secondPayment = Payment::find($second->json('payment.id'));

        $this->assertNotSame($firstPayment->transaction_reference, $secondPayment->transaction_reference);
    }

    /**
     * Retrying with a different payment_method (see initiate()'s own
     * docblock) must not let cash on delivery become reachable just
     * because it bypasses PlaceOrderRequest's validation (see
     * PaymentService::assertMethodEnabled()) — rejected for every
     * carrier now, not just Speedy (it used to be accepted for BOX NOW
     * specifically; that option was removed entirely, see
     * PaymentMethod::active()).
     */
    #[Test]
    public function retrying_with_cash_on_delivery_is_rejected_regardless_of_carrier(): void
    {
        $placed = $this->placeOrder();
        $orderId = $placed->json('data.id');
        $token = $placed->json('meta.guest_access_token');

        $this->postJson("/api/v1/payments/{$orderId}/initiate?token={$token}", ['payment_method' => 'cash_on_delivery'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');

        $variant = $this->purchasableVariant();
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $addToCart->json('meta.guest_token');

        $boxNowOrder = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/checkout/orders', [
            'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
            'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
            'shipping_carrier' => 'box_now',
            'shipping_delivery_type' => 'locker',
            'shipping_office_id' => 'locker-1',
            'shipping_office_name' => 'BOX NOW Mall of Sofia',
            'shipping_office_city' => 'Sofia',
            'shipping_office_address' => 'Mall of Sofia, bul. Alexander Malinov 1',
            'legal_document_ids' => $this->acceptAllCurrentLegalDocuments(),
        ])->assertCreated();
        $boxNowOrderId = $boxNowOrder->json('data.id');
        $boxNowToken = $boxNowOrder->json('meta.guest_access_token');

        $this->postJson("/api/v1/payments/{$boxNowOrderId}/initiate?token={$boxNowToken}", ['payment_method' => 'cash_on_delivery'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');
    }
}
