<?php

namespace Tests\Feature\Checkout;

use App\Enums\Currency;
use App\Enums\LegalDocumentType;
use App\Enums\OrderStatus;
use App\Enums\VariantStatus;
use App\Exceptions\Order\InvalidOrderStatusTransitionException;
use App\Models\LegalDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Exercises OrderService::confirmPayment()/cancel() directly — these are
 * the payment-integration seam described in OrderService's docblock, with
 * no HTTP route yet since no payment gateway is wired up in this sprint.
 */
class OrderFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableVariant(int $stock = 10): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => $stock]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 10]);

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

    private function placePendingOrder(ProductVariant $variant, int $quantity = 3): Order
    {
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => $quantity]);
        $guestToken = $addToCart->json('meta.guest_token');

        $placed = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/checkout/orders', [
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
            'shipping_carrier' => 'econt',
            'legal_document_ids' => $this->acceptAllCurrentLegalDocuments(),
        ]);

        return Order::findOrFail($placed->json('data.id'));
    }

    #[Test]
    public function confirming_payment_commits_stock_and_marks_the_order_processing(): void
    {
        $variant = $this->purchasableVariant(10);
        $order = $this->placePendingOrder($variant, 3);

        $confirmed = app(OrderService::class)->confirmPayment($order);

        $this->assertSame(OrderStatus::Processing, $confirmed->status);

        $variant->inventory->refresh();
        $this->assertSame(7, $variant->inventory->quantity_on_hand);
        $this->assertSame(0, $variant->inventory->quantity_reserved);
    }

    #[Test]
    public function cancelling_a_pending_order_releases_held_stock_without_decrementing_it(): void
    {
        $variant = $this->purchasableVariant(10);
        $order = $this->placePendingOrder($variant, 3);

        $cancelled = app(OrderService::class)->cancel($order);

        $this->assertSame(OrderStatus::Cancelled, $cancelled->status);

        $variant->inventory->refresh();
        $this->assertSame(10, $variant->inventory->quantity_on_hand);
        $this->assertSame(0, $variant->inventory->quantity_reserved);
    }

    #[Test]
    public function confirming_payment_twice_is_rejected(): void
    {
        $variant = $this->purchasableVariant(10);
        $order = $this->placePendingOrder($variant, 1);

        app(OrderService::class)->confirmPayment($order);

        $this->expectException(InvalidOrderStatusTransitionException::class);
        app(OrderService::class)->confirmPayment($order);
    }

    #[Test]
    public function cancelling_an_already_processing_order_is_rejected(): void
    {
        $variant = $this->purchasableVariant(10);
        $order = $this->placePendingOrder($variant, 1);

        app(OrderService::class)->confirmPayment($order);

        $this->expectException(InvalidOrderStatusTransitionException::class);
        app(OrderService::class)->cancel($order);
    }
}
