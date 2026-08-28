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
     * Card is the only PaymentMethod ever listed now — wallet brands are
     * never a separate checkout option (only rendered inside the iCard
     * modal), and cash on delivery was removed entirely (see
     * PaymentMethod::active()), not just marked unavailable.
     */
    #[Test]
    public function checkout_lists_only_card(): void
    {
        config([
            'services.apple_pay.enabled' => true,
            'services.icard.apple_pay_enabled' => true,
            'services.google_pay.enabled' => true,
            'services.icard.google_pay_enabled' => true,
        ]);

        $response = $this->getJson('/api/v1/checkout/payment-methods')->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['value' => 'card', 'available' => true]);
    }

    /**
     * Cash on delivery is rejected for every carrier now, not just
     * Speedy — it used to be accepted for BOX NOW specifically (its own
     * courier collected cash in person at hand-off), but that option was
     * removed entirely (see PaymentMethod::active()).
     */
    #[Test]
    public function cash_on_delivery_is_rejected_regardless_of_carrier(): void
    {
        $this->placeOrder('cash_on_delivery')->assertUnprocessable()->assertJsonValidationErrors('payment_method');

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
