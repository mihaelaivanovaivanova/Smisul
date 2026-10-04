<?php

namespace Tests\Feature;

use App\Enums\ShippingCarrier;
use App\Models\ShippingProviderSetting;
use Database\Seeders\ShippingProviderSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShippingProviderSettingsSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_the_speedy_row_with_a_080_cod_fee_when_none_exists(): void
    {
        (new ShippingProviderSettingsSeeder)->run();

        $this->assertDatabaseHas('shipping_provider_settings', [
            'provider' => ShippingCarrier::Speedy->value,
            'cod_fee' => 0.80,
        ]);
    }

    #[Test]
    public function it_fills_in_cod_fee_on_an_existing_row_that_has_it_unset(): void
    {
        $row = ShippingProviderSetting::query()->create([
            'provider' => ShippingCarrier::Speedy->value,
            'enabled' => true,
            'price_office' => 4.50,
        ]);

        (new ShippingProviderSettingsSeeder)->run();

        $this->assertEquals(0.80, $row->refresh()->cod_fee);
        $this->assertTrue($row->enabled);
        $this->assertEquals(4.50, $row->price_office);
    }

    #[Test]
    public function it_never_overwrites_an_admin_configured_cod_fee(): void
    {
        $row = ShippingProviderSetting::query()->create([
            'provider' => ShippingCarrier::Speedy->value,
            'cod_fee' => 1.20,
        ]);

        (new ShippingProviderSettingsSeeder)->run();

        $this->assertEquals(1.20, $row->refresh()->cod_fee);
    }
}
