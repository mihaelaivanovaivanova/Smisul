<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FunnelSeeder's default comparison row labels were shortened, but the
 * seeder keys its updateOrCreate on an already-seeded identity
 * (content_blocks: key) and so silently no-ops on any environment seeded
 * before this change - including production. Pushes the new labels there
 * directly, the same way 2026_09_19_000000_update_funnel_hero_title_default
 * did for the hero title. Only rows whose label still matches the old
 * default exactly are renamed - an admin's own edit to a row label is left
 * alone.
 */
return new class extends Migration
{
    private const LABEL_RENAMES = [
        'Използва се без паста' => 'Без паста',
        'Използва се навсякъде и по всяко време' => 'Навсякъде и по всяко време',
        'Без микропластмаса - изцяло растителен' => 'Без пластмаса',
        '100% биоразградим - нулев отпадък' => '100% биоразградим',
        'Без консумативи - отрязваш върха и продължаваш' => 'Без консумативи',
    ];

    public function up(): void
    {
        $block = DB::table('content_blocks')->where('key', 'funnel.comparison')->first();

        if ($block === null) {
            return;
        }

        $content = json_decode($block->content, true);
        $rows = $content['rows'] ?? [];
        $changed = false;

        foreach ($rows as $index => $row) {
            $label = $row['label'] ?? null;

            if ($label !== null && array_key_exists($label, self::LABEL_RENAMES)) {
                $rows[$index]['label'] = self::LABEL_RENAMES[$label];
                $changed = true;
            }
        }

        if (! $changed) {
            return;
        }

        $content['rows'] = $rows;

        DB::table('content_blocks')
            ->where('key', 'funnel.comparison')
            ->update([
                'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Content-only copy change with no meaningful prior state to
        // restore to - reverting would just reintroduce the old labels.
    }
};
