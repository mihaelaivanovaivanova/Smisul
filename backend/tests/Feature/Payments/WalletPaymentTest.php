<?php

namespace Tests\Feature\Payments;

use App\Enums\Currency;
use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WalletPaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Idempotent — this test calls placeOrder() (and so this helper) more
     * than once per test method, and a bare factory()->create() would
     * collide on the (type, version) unique constraint the second time.
     *
     * @return list<int>
     */
    private function legalDocuments(): array
    {
        return collect(LegalDocumentType::cases())->map(function (LegalDocumentType $type) {
            $existing = LegalDocument::where('type', $type)->where('version', '1.0')->first();

            return $existing?->id ?? LegalDocument::factory()->create(['type' => $type, 'version' => '1.0'])->id;
        })->all();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function placeOrder(string $method, array $overrides = [])
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 15]);
        $cart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);

        return $this->withHeaders(['X-Guest-Cart-Token' => $cart->json('meta.guest_token')])
            ->postJson('/api/v1/checkout/orders', array_merge([
                'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
                'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
                'shipping_carrier' => 'speedy',
                'shipping_delivery_type' => 'address',
                'legal_document_ids' => $this->legalDocuments(),
                'payment_method' => $method,
            ], $overrides));
    }

    /**
     * Wallet brands are never a separate checkout option (only rendered
     * inside the iCard modal) — card and cash on delivery are the only
     * two PaymentMethodResource ever lists (see
     * PaymentService::offerableMethods()), regardless of wallet config.
     * No carrier query param here, so cash on delivery is listed but
     * unavailable — see checkout_lists_cash_on_delivery_as_available_for_speedy
     * for the Speedy case.
     */
    #[Test]
    public function checkout_lists_card_and_cash_on_delivery(): void
    {
        config([
            'services.apple_pay.enabled' => true,
            'services.icard.apple_pay_enabled' => true,
            'services.google_pay.enabled' => true,
            'services.icard.google_pay_enabled' => true,
        ]);

        $response = $this->getJson('/api/v1/checkout/payment-methods')->assertOk()->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['value' => 'card', 'available' => true, 'fee' => 0.0]);
        $response->assertJsonFragment(['value' => 'cash_on_delivery', 'available' => false, 'fee' => 0.5]);
    }

    #[Test]
    public function checkout_lists_cash_on_delivery_as_available_for_speedy(): void
    {
        $response = $this->getJson('/api/v1/checkout/payment-methods?carrier=speedy')->assertOk();

        $response->assertJsonFragment(['value' => 'cash_on_delivery', 'available' => true]);
    }

    /**
     * Cash on delivery is only ever accepted for Speedy — its own courier
     * collects cash (or a card payment) in person at hand-off. BOX NOW's
     * locker network has no equivalent (nobody meets the customer), so it
     * stays rejected there.
     */
    #[Test]
    public function cash_on_delivery_is_accepted_for_speedy_and_rejected_for_box_now(): void
    {
        $this->placeOrder('cash_on_delivery')->assertCreated();

        $response = $this->placeOrder('cash_on_delivery', [
            'shipping_carrier' => 'box_now',
            'shipping_delivery_type' => 'locker',
            'shipping_office_id' => 'locker-1',
            'shipping_office_name' => 'BOX NOW Mall of Sofia',
            'shipping_office_city' => 'Sofia',
            'shipping_office_address' => 'Mall of Sofia, bul. Alexander Malinov 1',
        ]);
        $response->assertUnprocessable()->assertJsonValidationErrors('payment_method');
    }

    /**
     * The 0.50 surcharge (see PaymentMethod::fee()) lands on the order
     * itself, not just the payment - grand_total goes up by exactly that
     * much, and cod_fee shows it as its own real line item rather than
     * silently inflating shipping_total.
     */
    #[Test]
    public function cash_on_delivery_adds_its_fee_to_the_order_total(): void
    {
        $cardOrder = $this->placeOrder('card')->assertCreated();
        $codOrder = $this->placeOrder('cash_on_delivery')->assertCreated();

        $this->assertEquals(0.0, $cardOrder->json('data.totals.cod_fee'));
        $this->assertEquals(0.5, $codOrder->json('data.totals.cod_fee'));
        $this->assertEquals(
            round($cardOrder->json('data.totals.grand_total') + 0.5, 2),
            $codOrder->json('data.totals.grand_total'),
        );
    }

    /**
     * Retrying with a different method (see PaymentService::initiate()'s
     * own docblock) must reconcile the fee to match - switching back to
     * card after cash on delivery removes the surcharge again rather than
     * leaving it double-applied or stuck on an order that's no longer
     * paying that way.
     */
    #[Test]
    public function switching_from_cash_on_delivery_back_to_card_on_retry_removes_the_fee(): void
    {
        $placed = $this->placeOrder('cash_on_delivery')->assertCreated();
        $orderId = $placed->json('data.id');
        $token = $placed->json('meta.guest_access_token');
        $this->assertEquals(0.5, $placed->json('data.totals.cod_fee'));

        $originalGrandTotal = $placed->json('data.totals.grand_total');

        $this->postJson("/api/v1/payments/{$orderId}/initiate?token={$token}", ['payment_method' => 'card'])->assertOk();

        $order = $this->getJson("/api/v1/orders/{$orderId}?token={$token}")->assertOk();
        $this->assertEquals(0.0, $order->json('data.totals.cod_fee'));
        $this->assertEquals(round($originalGrandTotal - 0.5, 2), $order->json('data.totals.grand_total'));
    }

    #[Test]
    public function card_still_creates_the_single_icard_modal_session(): void
    {
        $response = $this->placeOrder('card')->assertCreated();
        $response->assertJsonPath('payment.provider', 'icard');
        $response->assertJsonPath('payment.payment_method', 'card');
        $this->assertNotNull($response->json('payment.modal_session.token'));
    }

    #[Test]
    public function separate_wallet_methods_are_rejected_even_when_wallet_flags_are_enabled(): void
    {
        config(['services.apple_pay.enabled' => true, 'services.icard.apple_pay_enabled' => true]);
        $this->placeOrder('apple_pay')->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->placeOrder('google_pay')->assertUnprocessable()->assertJsonValidationErrors('payment_method');
    }
}
