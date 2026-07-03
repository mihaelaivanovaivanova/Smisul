<?php

namespace Tests\Unit\Models;

use App\Models\Price;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PriceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_is_on_sale_when_compare_at_amount_is_higher(): void
    {
        $price = Price::factory()->make(['amount' => 15.00, 'compare_at_amount' => 20.00]);

        $this->assertTrue($price->isOnSale());
    }

    #[Test]
    public function it_is_not_on_sale_without_a_compare_at_amount(): void
    {
        $price = Price::factory()->make(['amount' => 15.00, 'compare_at_amount' => null]);

        $this->assertFalse($price->isOnSale());
    }

    #[Test]
    public function it_is_not_on_sale_when_compare_at_amount_is_lower_or_equal(): void
    {
        $lower = Price::factory()->make(['amount' => 15.00, 'compare_at_amount' => 10.00]);
        $equal = Price::factory()->make(['amount' => 15.00, 'compare_at_amount' => 15.00]);

        $this->assertFalse($lower->isOnSale());
        $this->assertFalse($equal->isOnSale());
    }
}
