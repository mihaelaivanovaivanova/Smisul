<?php

namespace Tests\Feature\Admin;

use App\Models\ShippingProviderSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShippingProviderSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_customer_cannot_view_or_update_shipping_settings(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson('/api/v1/admin/shipping-settings')->assertForbidden();
        $this->actingAs($customer)->putJson('/api/v1/admin/shipping-settings/speedy', ['enabled' => true])->assertForbidden();
    }

    #[Test]
    public function an_administrator_can_set_speedys_three_delivery_prices(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->putJson('/api/v1/admin/shipping-settings/speedy', [
            'enabled' => true,
            'base_url' => 'https://api.speedy.bg/v1/',
            'price_office' => 4.50,
            'price_locker' => 3.80,
            'price_address' => 6.20,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['provider' => 'speedy', 'price_office' => 4.5, 'price_locker' => 3.8, 'price_address' => 6.2]);

        $this->assertDatabaseHas('shipping_provider_settings', [
            'provider' => 'speedy',
            'price_office' => 4.5,
            'price_locker' => 3.8,
            'price_address' => 6.2,
        ]);
    }

    #[Test]
    public function an_administrator_can_set_box_nows_locker_price(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->putJson('/api/v1/admin/shipping-settings/box_now', [
            'enabled' => true,
            'base_url' => 'https://api-production.boxnow.bg/api/v1/',
            'price_locker' => 3.99,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['provider' => 'box_now', 'price_locker' => 3.99]);
    }

    #[Test]
    public function a_negative_price_is_rejected(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->putJson('/api/v1/admin/shipping-settings/speedy', [
            'enabled' => true,
            'price_office' => -1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['price_office']);
    }

    #[Test]
    public function clearing_a_price_field_removes_the_override(): void
    {
        $admin = User::factory()->administrator()->create();
        ShippingProviderSetting::query()->create([
            'provider' => 'speedy',
            'enabled' => true,
            'price_office' => 4.50,
        ]);

        $response = $this->actingAs($admin)->putJson('/api/v1/admin/shipping-settings/speedy', [
            'enabled' => true,
            'price_office' => null,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('shipping_provider_settings', ['provider' => 'speedy', 'price_office' => null]);
    }

    /**
     * Verifies the actual point of these prices: an admin override doesn't
     * just get stored, it changes what the public checkout catalog quotes
     * (see ShippingProviderSettingsService::priceFor() and each provider's
     * baseRate()) — and since OrderService charges exactly this catalog
     * price, it's also what the customer is actually billed.
     */
    #[Test]
    public function an_admin_configured_price_overrides_the_hardcoded_default_in_the_public_checkout_catalog(): void
    {
        ShippingProviderSetting::query()->create([
            'provider' => 'speedy',
            'enabled' => true,
            'price_office' => 3.49,
            'price_address' => 7.25,
        ]);

        $response = $this->getJson('/api/v1/checkout/shipping-methods');

        $response->assertOk();
        $response->assertJsonFragment(['carrier' => 'speedy', 'delivery_type' => 'office', 'price' => 3.49]);
        $response->assertJsonFragment(['carrier' => 'speedy', 'delivery_type' => 'address', 'price' => 7.25]);
    }

    #[Test]
    public function a_disabled_provider_row_does_not_override_the_price(): void
    {
        ShippingProviderSetting::query()->create([
            'provider' => 'speedy',
            'enabled' => false,
            'price_office' => 1.00,
        ]);

        $response = $this->getJson('/api/v1/checkout/shipping-methods');

        $response->assertOk();
        $response->assertJsonFragment(['carrier' => 'speedy', 'delivery_type' => 'office', 'price' => 5.99]);
    }
}
