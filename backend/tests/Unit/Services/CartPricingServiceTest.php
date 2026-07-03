<?php

namespace Tests\Unit\Services;

use App\Enums\Currency;
use App\Enums\VariantStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CartPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private CartPricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricing = new CartPricingService;
    }

    private function makeVariant(array $overrides = []): ProductVariant
    {
        $product = Product::factory()->published()->create();

        return ProductVariant::factory()
            ->for($product)
            ->create(array_merge(['status' => VariantStatus::Active], $overrides));
    }

    #[Test]
    public function a_variant_of_a_published_product_is_purchasable(): void
    {
        $variant = $this->makeVariant();

        $this->assertTrue($this->pricing->isVariantPurchasable($variant->fresh(['product'])));
    }

    #[Test]
    public function a_variant_of_an_unpublished_product_is_not_purchasable(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);

        $this->assertFalse($this->pricing->isVariantPurchasable($variant->fresh(['product'])));
    }

    #[Test]
    public function an_inactive_variant_is_not_purchasable(): void
    {
        $variant = $this->makeVariant(['status' => VariantStatus::Inactive]);

        $this->assertFalse($this->pricing->isVariantPurchasable($variant->fresh(['product'])));
    }

    #[Test]
    public function max_quantity_is_capped_by_available_stock(): void
    {
        $variant = $this->makeVariant();
        $variant->inventory()->create(['quantity_on_hand' => 7, 'quantity_reserved' => 2]);

        $this->assertSame(5, $this->pricing->maxQuantityFor($variant->fresh(['inventory'])));
    }

    #[Test]
    public function max_quantity_is_capped_by_the_absolute_ceiling_when_stock_is_effectively_unlimited(): void
    {
        $variant = $this->makeVariant();
        $variant->inventory()->create(['quantity_on_hand' => 0, 'backorders_allowed' => true]);

        $this->assertSame(CartPricingService::MAX_QUANTITY_PER_ITEM, $this->pricing->maxQuantityFor($variant->fresh(['inventory'])));
    }

    #[Test]
    public function max_quantity_is_zero_when_there_is_no_inventory_record(): void
    {
        $variant = $this->makeVariant();

        $this->assertSame(0, $this->pricing->maxQuantityFor($variant->fresh(['inventory'])));
    }

    #[Test]
    public function max_quantity_adds_back_what_this_line_already_holds(): void
    {
        // 3 of the 10 on hand are already reserved by this very line, so
        // its own ceiling isn't just the remaining 7 — it's 7 + the 3 it
        // already holds.
        $variant = $this->makeVariant();
        $variant->inventory()->create(['quantity_on_hand' => 10, 'quantity_reserved' => 3]);

        $this->assertSame(10, $this->pricing->maxQuantityFor($variant->fresh(['inventory']), alreadyHeldByThisLine: 3));
    }

    #[Test]
    public function an_item_is_unavailable_when_reserved_stock_exceeds_what_is_physically_on_hand(): void
    {
        // Reservation is enforced at write time, so a line's own quantity
        // can't outgrow stock on its own — this can only happen via an
        // external change to the inventory row (e.g. an admin correcting
        // the on-hand count after the reservation was already made).
        $variant = $this->makeVariant();
        $inventory = $variant->inventory()->create(['quantity_on_hand' => 3, 'quantity_reserved' => 3]);
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->for($cart)->for($variant, 'productVariant')->create(['quantity' => 3]);

        $inventory->update(['quantity_on_hand' => 1]);

        $this->assertFalse($this->pricing->isItemAvailable($item->fresh(['productVariant.product', 'productVariant.inventory'])));
    }

    #[Test]
    public function an_item_is_available_when_on_hand_still_covers_total_reservations(): void
    {
        $variant = $this->makeVariant();
        $variant->inventory()->create(['quantity_on_hand' => 5, 'quantity_reserved' => 3]);
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->for($cart)->for($variant, 'productVariant')->create(['quantity' => 3]);

        $this->assertTrue($this->pricing->isItemAvailable($item->fresh(['productVariant.product', 'productVariant.inventory'])));
    }

    #[Test]
    public function an_item_is_available_when_backorders_are_allowed_regardless_of_stock(): void
    {
        $variant = $this->makeVariant();
        $variant->inventory()->create(['quantity_on_hand' => 0, 'backorders_allowed' => true]);
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->for($cart)->for($variant, 'productVariant')->create(['quantity' => 10]);

        $this->assertTrue($this->pricing->isItemAvailable($item->fresh(['productVariant.product', 'productVariant.inventory'])));
    }

    #[Test]
    public function line_total_multiplies_unit_price_by_quantity(): void
    {
        $variant = $this->makeVariant();
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 12.50]);
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->for($cart)->for($variant, 'productVariant')->create(['quantity' => 3]);

        $this->assertSame(37.5, $this->pricing->lineTotal($item->fresh(['productVariant.prices']), Currency::EUR->value));
    }

    #[Test]
    public function line_total_is_zero_when_no_price_exists_for_the_currency(): void
    {
        $variant = $this->makeVariant();
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->for($cart)->for($variant, 'productVariant')->create(['quantity' => 3]);

        $this->assertSame(0.0, $this->pricing->lineTotal($item->fresh(['productVariant.prices']), Currency::EUR->value));
    }

    #[Test]
    public function totals_sum_line_totals_into_the_subtotal_and_grand_total(): void
    {
        $cart = Cart::factory()->create();

        $variantA = $this->makeVariant();
        $variantA->prices()->create(['currency' => Currency::EUR->value, 'amount' => 10]);
        CartItem::factory()->for($cart)->for($variantA, 'productVariant')->create(['quantity' => 2]);

        $variantB = $this->makeVariant();
        $variantB->prices()->create(['currency' => Currency::EUR->value, 'amount' => 5]);
        CartItem::factory()->for($cart)->for($variantB, 'productVariant')->create(['quantity' => 1]);

        $cart = $cart->fresh(['items.productVariant.prices']);
        $totals = $this->pricing->totals($cart);

        $this->assertSame(25.0, $totals->subtotal);
        $this->assertSame(0.0, $totals->discountTotal);
        $this->assertSame(0.0, $totals->shippingTotal);
        $this->assertSame(0.0, $totals->taxTotal);
        $this->assertSame(25.0, $totals->grandTotal);
        $this->assertSame(Currency::EUR->value, $totals->currency);
    }
}
