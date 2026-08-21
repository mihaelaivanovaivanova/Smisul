<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $settings = [
            ['key' => 'general.box_now_banner_enabled', 'type' => 'boolean', 'label' => 'Show Box Now top banner', 'value' => '1'],
            ['key' => 'general.box_now_badge_enabled', 'type' => 'boolean', 'label' => 'Show Box Now floating badge', 'value' => '1'],
            ['key' => 'general.box_now_banner_message', 'type' => 'string', 'label' => 'Box Now top banner message', 'value' => 'Безплатна доставка с Box Now до края на септември!'],
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
                'general.box_now_banner_enabled',
                'general.box_now_badge_enabled',
                'general.box_now_banner_message',
            ])
            ->delete();
    }
};
