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

    private function placeOrder(string $method)
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 15]);
        $cart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);

        return $this->withHeaders(['X-Guest-Cart-Token' => $cart->json('meta.guest_token')])
            ->postJson('/api/v1/checkout/orders', [
                'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
                'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
                'shipping_carrier' => 'speedy',
                'shipping_delivery_type' => 'address',
                'legal_document_ids' => $this->legalDocuments(),
                'payment_method' => $method,
            ]);
    }

    #[Test]
    public function checkout_exposes_only_the_single_icard_modal_method(): void
    {
        // Cash on delivery is temporarily withheld from checkout (see
        // PaymentService::availablePaymentMethods()) — re-adding it there
        // is the only change needed to turn it back on everywhere.
        config([
            'services.apple_pay.enabled' => true,
            'services.icard.apple_pay_enabled' => true,
            'services.google_pay.enabled' => true,
            'services.icard.google_pay_enabled' => true,
        ]);

        $response = $this->getJson('/api/v1/checkout/payment-methods')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(['card'], collect($response->json('data'))->pluck('value')->all());
    }

    #[Test]
    public function cash_on_delivery_is_rejected_at_checkout_while_withheld(): void
    {
        $this->placeOrder('cash_on_delivery')->assertUnprocessable()->assertJsonValidationErrors('payment_method');
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
