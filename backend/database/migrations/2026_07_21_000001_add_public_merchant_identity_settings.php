<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $settings = [
            ['key' => 'general.store_email', 'label' => 'Store contact email', 'value' => 'contact@smisul.bg'],
            ['key' => 'general.company_name', 'label' => 'Company legal name', 'value' => 'Филчев Уеб ЕООД'],
            ['key' => 'general.company_name_en', 'label' => 'Company legal name (English)', 'value' => 'Filchev Web LTD'],
            ['key' => 'general.company_manager', 'label' => 'Company manager', 'value' => 'Владимир Стоянов Филчев'],
            ['key' => 'general.company_id', 'label' => 'Company ID (ЕИК)', 'value' => '208699419'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'group' => 'general',
                    'type' => 'string',
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
            ->whereIn('key', ['general.company_name_en', 'general.company_manager'])
            ->delete();
    }
};
