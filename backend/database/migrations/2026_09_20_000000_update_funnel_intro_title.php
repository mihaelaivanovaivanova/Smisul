<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FunnelSeeder's default "What Is Miswak" (funnel.intro) title changed
 * again, but the seeder keys its updateOrCreate on an already-seeded
 * identity (content_blocks: key) and so silently no-ops on any
 * environment seeded before this change - including production. Pushes
 * the new title there directly, the same way
 * 2026_09_19_000000_update_funnel_hero_title_default did for the hero.
 * Guarded on the title 2026_09_19_030000_update_funnel_intro_content set
 * so an admin's own rewrite of this title is left alone rather than
 * overwritten.
 */
return new class extends Migration
{
    private const OLD_TITLE = 'Какво прави една пръчица толкова добра в почистването?';

    private const NEW_TITLE = 'Тайната зад Miswak - разкрита';

    public function up(): void
    {
        $block = DB::table('content_blocks')->where('key', 'funnel.intro')->first();

        if ($block === null) {
            return;
        }

        $content = json_decode($block->content, true);

        if (($content['title'] ?? null) !== self::OLD_TITLE) {
            return;
        }

        $content['title'] = self::NEW_TITLE;

        DB::table('content_blocks')
            ->where('key', 'funnel.intro')
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
