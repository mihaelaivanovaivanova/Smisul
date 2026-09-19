<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FunnelSeeder's default hero title changed, but the seeder keys its
 * updateOrCreate on an already-seeded identity (content_blocks: key) and
 * so silently no-ops on any environment seeded before this change -
 * including production. Pushes the new title there directly, the same
 * way 2026_09_14_190000_update_legal_documents_and_funnel_cta_for_speedy_cod
 * did for its own seeder-can't-reach-it gap. Only the base "funnel.hero"
 * row is touched - an ad-angle variant's own hero override (stored under
 * "funnel.variant.{slug}.hero") is a separate, intentional admin edit and
 * is left alone.
 */
return new class extends Migration
{
    private const OLD_TITLE = 'Не ти ли писна зъбите ти да са чисти... АМА САМО ПОНЯКОГА?';

    private const NEW_TITLE = 'Не ти ли писна от пластмасата в банята ти... ИМА И ДРУГ НАЧИН!';

    public function up(): void
    {
        $block = DB::table('content_blocks')->where('key', 'funnel.hero')->first();

        if ($block === null) {
            return;
        }

        $content = json_decode($block->content, true);

        if (($content['title'] ?? null) !== self::OLD_TITLE) {
            return;
        }

        $content['title'] = self::NEW_TITLE;

        DB::table('content_blocks')
            ->where('key', 'funnel.hero')
            ->update([
                'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Content-only copy change with no meaningful prior state to
        // restore to - reverting would just reintroduce the old title.
    }
};
