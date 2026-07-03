<?php

namespace Tests\Unit\Services;

use App\DataTransferObjects\ProductVariantData;
use App\Exceptions\DuplicateSkuException;
use App\Models\Product;
use App\Services\ProductVariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductVariantServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariantService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProductVariantService;
    }

    #[Test]
    public function it_creates_a_variant_with_an_inventory_row(): void
    {
        $product = Product::factory()->create();

        $variant = $this->service->create($product, new ProductVariantData(
            sku: 'SKU-1',
            name: '1-pack',
            packSize: 1,
        ));

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'sku' => 'SKU-1']);
        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 0,
        ]);
    }

    #[Test]
    public function it_rejects_a_duplicate_sku_on_create(): void
    {
        $product = Product::factory()->create();
        $this->service->create($product, new ProductVariantData(sku: 'DUP-1', name: '1-pack', packSize: 1));

        $this->expectException(DuplicateSkuException::class);

        $this->service->create($product, new ProductVariantData(sku: 'DUP-1', name: '3-pack', packSize: 3));
    }

    #[Test]
    public function updating_a_variant_may_keep_its_own_sku(): void
    {
        $product = Product::factory()->create();
        $variant = $this->service->create($product, new ProductVariantData(sku: 'SKU-1', name: '1-pack', packSize: 1));

        $updated = $this->service->update($variant, new ProductVariantData(sku: 'SKU-1', name: 'Renamed', packSize: 1));

        $this->assertSame('Renamed', $updated->name);
    }

    #[Test]
    public function making_a_variant_default_unsets_the_previous_default(): void
    {
        $product = Product::factory()->create();
        $first = $this->service->create($product, new ProductVariantData(
            sku: 'SKU-1',
            name: '1-pack',
            packSize: 1,
            isDefault: true,
        ));

        $second = $this->service->create($product, new ProductVariantData(
            sku: 'SKU-2',
            name: '3-pack',
            packSize: 3,
            isDefault: true,
        ));

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }
}
