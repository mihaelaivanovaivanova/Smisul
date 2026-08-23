<?php

namespace Tests\Feature\Checkout;

use App\Enums\Currency;
use App\Enums\LegalDocumentType;
use App\Mail\OrderConfirmationMail;
use App\Models\LegalDocument;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderConfirmationEmailTest extends TestCase
{
    use RefreshDatabase;

    /**
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
    private function placeOrder(array $overrides = [])
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
            ], $overrides));
    }

    #[Test]
    public function a_card_order_confirmation_email_does_not_mention_the_stale_payment_placeholder(): void
    {
        Mail::fake();

        $this->placeOrder()->assertCreated();

        Mail::assertSent(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) {
            $rendered = $mail->render();

            return $mail->hasTo('ivan@example.com')
                && str_contains($rendered, 'Обработваме плащането с карта')
                && ! str_contains($rendered, 'следваща стъпка')
                && ! str_contains($rendered, 'BOX NOW');
        });
    }

    #[Test]
    public function a_cash_on_delivery_order_confirmation_email_says_so(): void
    {
        Mail::fake();

        $this->placeOrder([
            'shipping_carrier' => 'box_now',
            'shipping_delivery_type' => 'locker',
            'shipping_office_id' => 'locker-1',
            'shipping_office_name' => 'BOX NOW Mall of Sofia',
            'shipping_office_city' => 'Sofia',
            'shipping_office_address' => 'Mall of Sofia, bul. Alexander Malinov 1',
            'payment_method' => 'cash_on_delivery',
        ])->assertCreated();

        Mail::assertSent(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) {
            $rendered = $mail->render();

            // Not "в брой на куриера" — BOX NOW is an unmanned locker
            // network, so cash-on-delivery there is actually a card charge
            // through BOX NOW's own payment portal at pickup, not cash
            // handed to a courier.
            return str_contains($rendered, 'Плащаш с банкова карта чрез BOX NOW')
                && ! str_contains($rendered, 'Обработваме плащането с карта');
        });
    }
}
