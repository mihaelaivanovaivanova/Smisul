<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Seeds the editable admin settings with sane defaults. Payments and
     * Shipping are intentionally absent — those sections of the admin
     * Settings UI are read-only, computed from env/config (see
     * Admin\SettingController::providerStatus()), not stored here.
     */
    public function run(): void
    {
        $defaults = [
            ['key' => 'general.store_name', 'group' => 'general', 'type' => 'string', 'label' => 'Store name', 'value' => 'Smisul'],
            ['key' => 'general.store_email', 'group' => 'general', 'type' => 'string', 'label' => 'Store contact email', 'value' => null],
            ['key' => 'general.support_phone', 'group' => 'general', 'type' => 'string', 'label' => 'Support phone', 'value' => null],
            ['key' => 'general.contact_address', 'group' => 'general', 'type' => 'string', 'label' => 'Contact address', 'value' => null],
            // Legal merchant identity shown in the storefront footer once
            // filled in (see SettingService::publicSettings) — BG shops are
            // expected to display фирма/ЕИК publicly.
            ['key' => 'general.company_name', 'group' => 'general', 'type' => 'string', 'label' => 'Company legal name', 'value' => null],
            ['key' => 'general.company_id', 'group' => 'general', 'type' => 'string', 'label' => 'Company ID (ЕИК)', 'value' => null],

            ['key' => 'email.from_name', 'group' => 'email', 'type' => 'string', 'label' => 'Email "from" name', 'value' => 'Smisul'],
            ['key' => 'email.from_address', 'group' => 'email', 'type' => 'string', 'label' => 'Email "from" address', 'value' => null],

            ['key' => 'seo.default_meta_title', 'group' => 'seo', 'type' => 'string', 'label' => 'Default meta title', 'value' => null],
            ['key' => 'seo.default_meta_description', 'group' => 'seo', 'type' => 'string', 'label' => 'Default meta description', 'value' => null],
            ['key' => 'seo.default_og_image', 'group' => 'seo', 'type' => 'string', 'label' => 'Default social share image URL', 'value' => null],

            ['key' => 'media.max_upload_size_mb', 'group' => 'media', 'type' => 'integer', 'label' => 'Max upload size (MB)', 'value' => '10'],

            ['key' => 'system.maintenance_mode', 'group' => 'system', 'type' => 'boolean', 'label' => 'Maintenance mode', 'value' => '0'],
        ];

        foreach ($defaults as $setting) {
            Setting::query()->firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
