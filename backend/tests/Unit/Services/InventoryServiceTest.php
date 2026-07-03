<?php

namespace Tests\Unit\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InventoryService;
    }

    #[Test]
    public function it_increases_stock(): void
    {
        $inventory = Inventory::factory()->create(['quantity_on_hand' => 10]);

        $updated = $this->service->increaseStock($inventory, 5);

        $this->assertSame(15, $updated->quantity_on_hand);
    }

    #[Test]
    public function it_decreases_stock_when_enough_is_available(): void
    {
        $inventory = Inventory::factory()->create(['quantity_on_hand' => 10]);

        $updated = $this->service->decreaseStock($inventory, 4);

        $this->assertSame(6, $updated->quantity_on_hand);
    }

    #[Test]
    public function it_refuses_to_decrease_stock_below_zero_without_backorders(): void
    {
        $variant = ProductVariant::factory()->create(['sku' => 'SKU-LOW']);
        $inventory = Inventory::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 3,
            'backorders_allowed' => false,
        ]);

        $this->expectException(InsufficientStockException::class);

        $this->service->decreaseStock($inventory, 10);
    }

    #[Test]
    public function it_allows_decreasing_below_zero_available_when_backorders_are_allowed(): void
    {
        $inventory = Inventory::factory()->create([
            'quantity_on_hand' => 3,
            'backorders_allowed' => true,
        ]);

        $updated = $this->service->decreaseStock($inventory, 10);

        $this->assertSame(-7, $updated->quantity_on_hand);
    }

    #[Test]
    public function reserving_stock_increases_reserved_quantity(): void
    {
        $inventory = Inventory::factory()->create(['quantity_on_hand' => 10, 'quantity_reserved' => 0]);

        $updated = $this->service->reserve($inventory, 3);

        $this->assertSame(3, $updated->quantity_reserved);
        $this->assertSame(7, $updated->availableQuantity());
    }

    #[Test]
    public function releasing_reserved_stock_never_goes_negative(): void
    {
        $inventory = Inventory::factory()->create(['quantity_reserved' => 2]);

        $updated = $this->service->release($inventory, 10);

        $this->assertSame(0, $updated->quantity_reserved);
    }
}
