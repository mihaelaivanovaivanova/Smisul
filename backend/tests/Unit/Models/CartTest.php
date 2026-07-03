<?php

namespace Tests\Unit\Models;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_reports_a_guest_cart_correctly(): void
    {
        $cart = Cart::factory()->create();

        $this->assertTrue($cart->isGuestCart());
    }

    #[Test]
    public function it_reports_a_user_cart_correctly(): void
    {
        $cart = Cart::factory()->forUser(User::factory()->create())->create();

        $this->assertFalse($cart->isGuestCart());
    }

    #[Test]
    public function total_quantity_sums_all_item_quantities(): void
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)->create(['quantity' => 2]);
        CartItem::factory()->for($cart)->create(['quantity' => 5]);

        $this->assertSame(7, $cart->fresh(['items'])->totalQuantity());
    }
}
