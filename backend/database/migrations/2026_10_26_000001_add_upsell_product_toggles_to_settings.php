<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $settings = [
            ['key' => 'general.upsell_bamboo_case_enabled', 'type' => 'boolean', 'label' => 'Show bamboo case in upsell (cart/checkout)', 'value' => '1'],
            ['key' => 'general.upsell_tongue_scraper_enabled', 'type' => 'boolean', 'label' => 'Show tongue scraper in upsell (cart/checkout)', 'value' => '1'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'group' => 'general',
                    'type' => $setting['type'],
                    'label' => $setting['label'],
                    'value' => $setting['value'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->whereIn('key', [
                'general.upsell_bamboo_case_enabled',
                'general.upsell_tongue_scraper_enabled',
            ])
            ->delete();
    }
};
