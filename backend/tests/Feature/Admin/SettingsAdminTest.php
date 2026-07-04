<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_customer_cannot_view_settings(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson('/api/v1/admin/settings')->assertForbidden();
    }

    #[Test]
    public function a_guest_is_unauthenticated_on_settings_endpoints(): void
    {
        $this->getJson('/api/v1/admin/settings')->assertUnauthorized();
    }

    #[Test]
    public function an_administrator_sees_editable_settings_grouped_and_provider_status(): void
    {
        $admin = User::factory()->administrator()->create();
        Setting::query()->create([
            'key' => 'general.store_name', 'group' => 'general', 'type' => 'string',
            'label' => 'Store name', 'value' => 'Smisul',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/settings');

        $response->assertOk();
        $response->assertJsonPath('data.editable.general.0.key', 'general.store_name');
        $response->assertJsonPath('data.editable.general.0.value', 'Smisul');
        $response->assertJsonStructure([
            'data' => ['editable', 'providers' => ['payments', 'shipping']],
        ]);
    }

    #[Test]
    public function an_administrator_can_update_a_setting_value(): void
    {
        $admin = User::factory()->administrator()->create();
        Setting::query()->create([
            'key' => 'general.store_name', 'group' => 'general', 'type' => 'string',
            'label' => 'Store name', 'value' => 'Old Name',
        ]);

        $response = $this->actingAs($admin)->putJson('/api/v1/admin/settings', [
            'settings' => ['general.store_name' => 'New Name'],
        ]);

        $response->assertOk();
        $this->assertSame('New Name', Setting::query()->where('key', 'general.store_name')->value('value'));
    }

    #[Test]
    public function updating_an_unknown_setting_key_is_rejected(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->putJson('/api/v1/admin/settings', [
            'settings' => ['not.a.real.key' => 'value'],
        ])->assertUnprocessable();
    }

    #[Test]
    public function boolean_settings_are_normalized_to_zero_or_one(): void
    {
        $admin = User::factory()->administrator()->create();
        Setting::query()->create([
            'key' => 'system.maintenance_mode', 'group' => 'system', 'type' => 'boolean',
            'label' => 'Maintenance mode', 'value' => '0',
        ]);

        $this->actingAs($admin)->putJson('/api/v1/admin/settings', [
            'settings' => ['system.maintenance_mode' => true],
        ])->assertOk();

        $this->assertSame('1', Setting::query()->where('key', 'system.maintenance_mode')->value('value'));
    }
}
