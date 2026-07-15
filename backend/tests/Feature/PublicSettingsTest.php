<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_exactly_the_whitelisted_merchant_keys(): void
    {
        $response = $this->getJson('/api/v1/settings/public');

        $response->assertOk();
        $this->assertSame(
            ['company_name', 'company_id', 'contact_address', 'support_phone', 'store_email'],
            array_keys($response->json('data')),
        );
    }

    #[Test]
    public function filled_settings_are_returned_and_unfilled_ones_are_null(): void
    {
        Setting::query()->create([
            'key' => 'general.company_name',
            'group' => 'general',
            'type' => 'string',
            'label' => 'Company legal name',
            'value' => 'Смисъл ЕООД',
        ]);

        $response = $this->getJson('/api/v1/settings/public');

        $response->assertOk();
        $response->assertJsonPath('data.company_name', 'Смисъл ЕООД');
        $response->assertJsonPath('data.company_id', null);
    }

    #[Test]
    public function non_whitelisted_settings_never_leak(): void
    {
        Setting::query()->create([
            'key' => 'general.store_name',
            'group' => 'general',
            'type' => 'string',
            'label' => 'Store name',
            'value' => 'Smisul',
        ]);

        $response = $this->getJson('/api/v1/settings/public');

        $response->assertOk();
        $this->assertArrayNotHasKey('store_name', $response->json('data'));
    }
}
