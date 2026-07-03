<?php

namespace Tests\Unit\Services;

use App\DataTransferObjects\PriceData;
use App\Enums\Currency;
use App\Models\ProductVariant;
use App\Services\PriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PriceServiceTest extends TestCase
{
    use RefreshDatabase;

    private PriceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PriceService;
    }

    #[Test]
    public function setting_a_price_for_the_first_time_creates_no_history_entry_with_an_old_amount(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->service->setPrice($variant, new PriceData(currency: Currency::BGN->value, amount: 19.99));

        $this->assertDatabaseHas('prices', [
            'product_variant_id' => $variant->id,
            'currency' => 'BGN',
            'amount' => 19.99,
        ]);
        $this->assertDatabaseHas('price_histories', [
            'product_variant_id' => $variant->id,
            'old_amount' => null,
            'new_amount' => 19.99,
        ]);
    }

    #[Test]
    public function changing_the_price_records_the_old_and_new_amounts(): void
    {
        $variant = ProductVariant::factory()->create();
        $this->service->setPrice($variant, new PriceData(currency: Currency::BGN->value, amount: 19.99));

        $this->service->setPrice($variant, new PriceData(currency: Currency::BGN->value, amount: 24.99));

        $this->assertDatabaseHas('prices', [
            'product_variant_id' => $variant->id,
            'currency' => 'BGN',
            'amount' => 24.99,
        ]);
        $this->assertDatabaseHas('price_histories', [
            'product_variant_id' => $variant->id,
            'old_amount' => 19.99,
            'new_amount' => 24.99,
        ]);
    }

    #[Test]
    public function setting_the_same_price_again_does_not_create_a_new_history_entry(): void
    {
        $variant = ProductVariant::factory()->create();
        $this->service->setPrice($variant, new PriceData(currency: Currency::BGN->value, amount: 19.99));

        $this->service->setPrice($variant, new PriceData(currency: Currency::BGN->value, amount: 19.99));

        $this->assertDatabaseCount('price_histories', 1);
    }
}
