<?php

namespace Tests\Feature\Admin;

use App\Models\ICardConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ICardConfigurationAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_admin_can_save_encrypted_sandbox_credentials_and_activate_them(): void
    {
        $admin = User::factory()->administrator()->create();
        $private = file_get_contents(base_path('tests/Fixtures/icard/test_private_key.pem'));
        $public = file_get_contents(base_path('tests/Fixtures/icard/test_public_key.pem'));

        $response = $this->actingAs($admin)->putJson('/api/v1/admin/payment-settings/icard/sandbox', [
            'enabled' => true, 'mid' => '000000000000000', 'mid_name' => 'Smisul',
            'originator' => '1', 'key_index' => '1', 'key_index_resp' => '1',
            'ipg_version' => '4.5', 'currency_numeric' => '978',
            'base_url' => 'https://dev-ipg.icards.eu/sandbox/',
            'modal_js_url' => 'https://dev-ipg.icards.eu/sandbox/js/payment-modal.js',
            'wallet_js_url' => 'https://dev-ipg.icards.eu/sandbox/js/icard-g-a-pay.min.js',
            'webhook_url' => 'https://smisul.bg/api/v1/payments/webhook/icard',
            'private_key' => $private, 'public_key' => $public,
            'callback_ips' => ['127.0.0.1'],
            'apple_pay_enabled' => true, 'google_pay_enabled' => true,
            'apple_merchant_id' => null, 'apple_merchant_domain' => 'smisul.bg',
            'google_merchant_id' => null, 'google_environment' => 'TEST',
        ]);

        $response->assertOk()->assertJsonMissing(['private_key' => $private, 'public_key' => $public]);
        $this->assertNotSame($private, DB::table('icard_configurations')->value('private_key'));
        $this->assertSame($private, ICardConfiguration::firstOrFail()->private_key);

        $this->actingAs($admin)->postJson('/api/v1/admin/payment-settings/icard/sandbox/activate')
            ->assertOk()->assertJsonPath('data.0.is_active', true);
    }

    #[Test]
    public function a_customer_cannot_change_icard_credentials(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/admin/payment-settings/icard')
            ->assertForbidden();
    }
}

