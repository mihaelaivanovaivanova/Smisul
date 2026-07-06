<?php

namespace Tests\Feature\Payments;

use App\Enums\Currency;
use App\Enums\LegalDocumentType;
use App\Enums\PaymentMethod;
use App\Exceptions\Payment\InvalidPaymentMethodException;
use App\Models\LegalDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WalletPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableVariant(int $stock = 10): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
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

    private function placeOrder(?string $paymentMethod = null): TestResponse
    {
        $variant = $this->purchasableVariant();
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $addToCart->json('meta.guest_token');

        $payload = [
            'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
            'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
            'shipping_carrier' => 'econt',
            'shipping_delivery_type' => 'address',
            'legal_document_ids' => $this->acceptAllCurrentLegalDocuments(),
        ];

        if ($paymentMethod !== null) {
            $payload['payment_method'] = $paymentMethod;
        }

        return $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/checkout/orders', $payload);
    }

    private function enableWallets(): void
    {
        config([
            'services.apple_pay.enabled' => true,
            'services.icard.apple_pay_enabled' => true,
            'services.google_pay.enabled' => true,
            'services.icard.google_pay_enabled' => true,
        ]);
    }

    #[Test]
    public function card_is_always_listed_and_is_the_default_when_wallets_are_disabled(): void
    {
        $response = $this->getJson('/api/v1/checkout/payment-methods');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.value', 'card');
    }

    #[Test]
    public function wallets_appear_in_the_list_once_both_their_flags_are_enabled(): void
    {
        $this->enableWallets();

        $response = $this->getJson('/api/v1/checkout/payment-methods');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $values = collect($response->json('data'))->pluck('value')->all();
        $this->assertContains('apple_pay', $values);
        $this->assertContains('google_pay', $values);
    }

    /**
     * Both the app-level flag AND iCard's own flag must be on — either one
     * alone must not be enough (see PaymentService::availablePaymentMethods).
     */
    #[Test]
    public function a_wallet_is_hidden_if_only_one_of_its_two_flags_is_enabled(): void
    {
        config(['services.apple_pay.enabled' => true, 'services.icard.apple_pay_enabled' => false]);

        $response = $this->getJson('/api/v1/checkout/payment-methods');

        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function card_payment_still_works_exactly_as_before(): void
    {
        $response = $this->placeOrder('card');

        $response->assertCreated();
        $response->assertJsonPath('payment.payment_method', 'card');
        $response->assertJsonPath('payment.provider', 'icard');
        $response->assertJsonPath('payment.status', 'initiated');
        $this->assertDatabaseHas('payments', ['payment_method' => 'card']);
    }

    #[Test]
    public function card_is_the_default_payment_method_when_none_is_specified(): void
    {
        $response = $this->placeOrder();

        $response->assertCreated();
        $response->assertJsonPath('payment.payment_method', 'card');
    }

    #[Test]
    public function an_order_can_be_placed_with_apple_pay_when_enabled(): void
    {
        $this->enableWallets();

        $response = $this->placeOrder('apple_pay');

        $response->assertCreated();
        $response->assertJsonPath('payment.payment_method', 'apple_pay');
        $response->assertJsonPath('payment.status', 'initiated');
        $this->assertDatabaseHas('payments', ['payment_method' => 'apple_pay']);
        $this->assertDatabaseHas('payment_transactions', ['type' => 'initiated', 'payment_method' => 'apple_pay']);
    }

    #[Test]
    public function an_order_can_be_placed_with_google_pay_when_enabled(): void
    {
        $this->enableWallets();

        $response = $this->placeOrder('google_pay');

        $response->assertCreated();
        $response->assertJsonPath('payment.payment_method', 'google_pay');
        $this->assertDatabaseHas('payments', ['payment_method' => 'google_pay']);
    }

    /**
     * The wallet-specific IPGPurchase field is additive — the rest of the
     * signed field set (and therefore the working card flow) is untouched.
     */
    #[Test]
    public function an_apple_pay_session_still_produces_a_valid_signed_form(): void
    {
        $this->enableWallets();

        $response = $this->placeOrder('apple_pay');

        $fields = $response->json('payment.form_fields');
        $this->assertIsArray($fields);
        $this->assertSame('IPGPurchase', $fields['IPGmethod']);
        $this->assertSame('ApplePay', $fields['PayMethod']);
        $this->assertArrayHasKey('Signature', $fields);
        $this->assertNotEmpty($fields['Signature']);
    }

    #[Test]
    public function apple_pay_is_rejected_when_disabled(): void
    {
        $response = $this->placeOrder('apple_pay');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('payment_method');
    }

    #[Test]
    public function google_pay_is_rejected_when_disabled(): void
    {
        $response = $this->placeOrder('google_pay');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('payment_method');
    }

    #[Test]
    public function an_unknown_payment_method_is_rejected(): void
    {
        $response = $this->placeOrder('bitcoin');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('payment_method');
    }

    /**
     * Defense-in-depth check on PaymentService itself, independent of the
     * FormRequest layer — see InvalidPaymentMethodException's docblock.
     */
    #[Test]
    public function payment_service_rejects_a_disabled_method_even_when_called_directly(): void
    {
        $variant = $this->purchasableVariant();
        $order = Order::factory()->create(['currency' => 'EUR']);

        $this->expectException(InvalidPaymentMethodException::class);

        app(PaymentService::class)->initiate($order, PaymentMethod::ApplePay);
    }

    #[Test]
    public function retrying_a_payment_can_switch_to_a_different_enabled_method(): void
    {
        $this->enableWallets();

        $placed = $this->placeOrder('card');
        $orderId = $placed->json('data.id');
        $token = $placed->json('meta.guest_access_token');

        $response = $this->postJson("/api/v1/payments/{$orderId}/initiate?token={$token}", [
            'payment_method' => 'google_pay',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.payment_method', 'google_pay');
    }

    #[Test]
    public function retrying_a_payment_with_a_disabled_method_is_rejected(): void
    {
        $placed = $this->placeOrder('card');
        $orderId = $placed->json('data.id');
        $token = $placed->json('meta.guest_access_token');

        $response = $this->postJson("/api/v1/payments/{$orderId}/initiate?token={$token}", [
            'payment_method' => 'google_pay',
        ]);

        $response->assertUnprocessable();
    }
}
