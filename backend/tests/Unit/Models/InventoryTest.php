<?php

namespace Tests\Unit\Models;

use App\Models\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function available_quantity_subtracts_reserved_from_on_hand(): void
    {
        $inventory = Inventory::factory()->make([
            'quantity_on_hand' => 10,
            'quantity_reserved' => 3,
        ]);

        $this->assertSame(7, $inventory->availableQuantity());
    }

    #[Test]
    public function available_quantity_never_goes_negative(): void
    {
        $inventory = Inventory::factory()->make([
            'quantity_on_hand' => 2,
            'quantity_reserved' => 5,
        ]);

        $this->assertSame(0, $inventory->availableQuantity());
    }

    #[Test]
    public function it_reports_low_stock_correctly(): void
    {
        $lowStock = Inventory::factory()->make([
            'quantity_on_hand' => 3,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 5,
        ]);
        $healthyStock = Inventory::factory()->make([
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 5,
        ]);

        $this->assertTrue($lowStock->isLowStock());
        $this->assertFalse($healthyStock->isLowStock());
    }

    #[Test]
    public function it_is_in_stock_when_backorders_are_allowed_even_at_zero_quantity(): void
    {
        $inventory = Inventory::factory()->make([
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
            'backorders_allowed' => true,
        ]);

        $this->assertTrue($inventory->isInStock());
    }

    #[Test]
    public function it_is_out_of_stock_at_zero_quantity_without_backorders(): void
    {
        $inventory = Inventory::factory()->make([
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
            'backorders_allowed' => false,
        ]);

        $this->assertFalse($inventory->isInStock());
    }
}
