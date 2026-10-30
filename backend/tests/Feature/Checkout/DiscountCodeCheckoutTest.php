<?php

namespace Tests\Feature\Checkout;

use App\Enums\Currency;
use App\Enums\LegalDocumentType;
use App\Enums\VariantStatus;
use App\Models\DiscountCode;
use App\Models\LegalDocument;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscountCodeCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableVariant(int $stock = 10): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => $stock]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 20.00]);

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

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'customer' => [
                'first_name' => 'Ivan',
                'last_name' => 'Ivanov',
                'email' => 'ivan@example.com',
                'phone' => '+359888123456',
            ],
            'address' => [
                'country' => 'Bulgaria',
                'city' => 'Sofia',
                'postal_code' => '1000',
                'address_line' => 'ul. Vitosha 1',
            ],
            'shipping_carrier' => 'speedy',
            'shipping_delivery_type' => 'address',
            'legal_document_ids' => $this->acceptAllCurrentLegalDocuments(),
        ], $overrides);
    }

    private function addToCartAndGetGuestToken(ProductVariant $variant, int $quantity = 1): string
    {
        $response = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => $quantity]);

        return $response->json('meta.guest_token');
    }

    #[Test]
    public function a_valid_code_passes_validation(): void
    {
        DiscountCode::create([
            'code' => 'MISWAK-ABC123',
            'customer_email' => 'ivan@example.com',
            'discount_percent' => 10,
            'max_uses' => 1,
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->postJson('/api/v1/checkout/discount-code/validate', [
            'code' => 'miswak-abc123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.discount_percent', 10);
    }

    /**
     * By request: customer_email on a DiscountCode (set when
     * IssueReviewRewardCode issues a review-reward code) is a record of
     * who earned it, not a redemption gate - DiscountCodeService never
     * compares it against the checkout email, so a code issued to one
     * address still validates for anyone who has it.
     */
    #[Test]
    public function a_code_issued_to_one_email_still_validates_for_a_different_one(): void
    {
        DiscountCode::create([
            'code' => 'MISWAK-ABC123',
            'customer_email' => 'someone-else@example.com',
            'discount_percent' => 10,
            'max_uses' => 1,
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->postJson('/api/v1/checkout/discount-code/validate', [
            'code' => 'MISWAK-ABC123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.discount_percent', 10);
    }

    #[Test]
    public function an_expired_code_fails_validation(): void
    {
        DiscountCode::create([
            'code' => 'MISWAK-OLD123',
            'customer_email' => 'ivan@example.com',
            'discount_percent' => 10,
            'max_uses' => 1,
            'expires_at' => now()->subDay(),
        ]);

        $this->postJson('/api/v1/checkout/discount-code/validate', [
            'code' => 'MISWAK-OLD123',
        ])->assertUnprocessable();
    }

    /**
     * A code scheduled for a future activation window (e.g. a one-day
     * promo) isn't redeemable yet, even though it's active and not
     * expired - see DiscountCode::isRedeemable()'s starts_at check.
     */
    #[Test]
    public function a_code_that_has_not_started_yet_fails_validation(): void
    {
        // Not SPOOKY10 - that code already exists, seeded by a migration
        // (see 2026_10_30_000002_add_spooky10_discount_code), so reusing
        // it here would collide with that pre-existing row.
        DiscountCode::create([
            'code' => 'FUTURE-PROMO',
            'discount_percent' => 10,
            'max_uses' => 500,
            'starts_at' => now()->addDay(),
            'expires_at' => now()->addDays(2),
        ]);

        $this->postJson('/api/v1/checkout/discount-code/validate', [
            'code' => 'FUTURE-PROMO',
        ])->assertUnprocessable();
    }

    #[Test]
    public function a_code_within_its_active_window_passes_validation(): void
    {
        DiscountCode::create([
            'code' => 'ACTIVE-PROMO',
            'discount_percent' => 10,
            'max_uses' => 500,
            'starts_at' => now()->subHour(),
            'expires_at' => now()->addHours(12),
        ]);

        $response = $this->postJson('/api/v1/checkout/discount-code/validate', [
            'code' => 'ACTIVE-PROMO',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.discount_percent', 10);
    }

    #[Test]
    public function placing_an_order_with_a_valid_code_discounts_the_grand_total(): void
    {
        $variant = $this->purchasableVariant();
        $guestToken = $this->addToCartAndGetGuestToken($variant, 1);
        DiscountCode::create([
            'code' => 'MISWAK-ABC123',
            'customer_email' => 'ivan@example.com',
            'discount_percent' => 10,
            'max_uses' => 1,
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->postJson('/api/v1/checkout/orders', $this->validPayload(['discount_code' => 'miswak-abc123']));

        $response->assertCreated();
        // subtotal 20.00, -10% = 2.00 discount, + 5.99 shipping = 23.99.
        $response->assertJsonPath('data.totals.subtotal', 20);
        $response->assertJsonPath('data.totals.discount_total', 2);
        $response->assertJsonPath('data.totals.grand_total', 23.99);
        $response->assertJsonPath('data.totals.discount_code', 'MISWAK-ABC123');

        $this->assertDatabaseHas('discount_codes', ['code' => 'MISWAK-ABC123', 'used_count' => 1]);
    }

    #[Test]
    public function placing_an_order_applies_a_code_issued_to_a_different_email(): void
    {
        $variant = $this->purchasableVariant();
        $guestToken = $this->addToCartAndGetGuestToken($variant, 1);
        DiscountCode::create([
            'code' => 'MISWAK-ABC123',
            'customer_email' => 'the-reviewer@example.com',
            'discount_percent' => 10,
            'max_uses' => 1,
            'expires_at' => now()->addDays(30),
        ]);

        // validPayload()'s default customer.email is 'ivan@example.com' -
        // deliberately different from the code's customer_email above.
        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->postJson('/api/v1/checkout/orders', $this->validPayload(['discount_code' => 'miswak-abc123']));

        $response->assertCreated();
        $response->assertJsonPath('data.totals.discount_code', 'MISWAK-ABC123');
        $response->assertJsonPath('data.totals.discount_total', 2);
    }

    #[Test]
    public function a_single_use_code_cannot_be_redeemed_twice(): void
    {
        $discountCode = DiscountCode::create([
            'code' => 'MISWAK-ONCE1',
            'customer_email' => null,
            'discount_percent' => 10,
            'max_uses' => 1,
            'expires_at' => now()->addDays(30),
        ]);

        $firstVariant = $this->purchasableVariant();
        $firstToken = $this->addToCartAndGetGuestToken($firstVariant, 1);
        $this->withHeaders(['X-Guest-Cart-Token' => $firstToken])
            ->postJson('/api/v1/checkout/orders', $this->validPayload(['discount_code' => 'MISWAK-ONCE1']))
            ->assertCreated();

        $discountCode->refresh();
        $this->assertSame(1, $discountCode->used_count);

        $secondVariant = $this->purchasableVariant();
        $secondToken = $this->addToCartAndGetGuestToken($secondVariant, 1);
        $response = $this->withHeaders(['X-Guest-Cart-Token' => $secondToken])
            ->postJson('/api/v1/checkout/orders', $this->validPayload(['discount_code' => 'MISWAK-ONCE1']));

        // The second order still succeeds (an invalid/exhausted code is
        // silently ignored, not a checkout-blocking error - same leniency
        // as e.g. a promo that expired mid-cart) but carries no discount.
        $response->assertCreated();
        $response->assertJsonPath('data.totals.discount_total', 0);
        $response->assertJsonPath('data.totals.discount_code', null);

        $discountCode->refresh();
        $this->assertSame(1, $discountCode->used_count);
    }

    #[Test]
    public function an_invalid_code_is_silently_ignored_rather_than_blocking_checkout(): void
    {
        $variant = $this->purchasableVariant();
        $guestToken = $this->addToCartAndGetGuestToken($variant, 1);

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->postJson('/api/v1/checkout/orders', $this->validPayload(['discount_code' => 'NOT-A-REAL-CODE']));

        $response->assertCreated();
        $response->assertJsonPath('data.totals.discount_total', 0);
    }
}
