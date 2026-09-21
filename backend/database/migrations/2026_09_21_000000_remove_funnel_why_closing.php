<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The "funnel.why" (Core Benefits) section's closing statement was removed
 * from the page entirely (CoreBenefitsSection.tsx no longer renders it, and
 * FunnelWhyContent dropped the field) - by request. The seeder keys its
 * updateOrCreate on an already-seeded identity (content_blocks: key) and so
 * silently no-ops on any environment seeded before this change - including
 * production. Drops the key from the stored content there directly, the
 * same way 2026_09_19_000000_update_funnel_hero_title_default did for the
 * hero. Guarded on the OLD closing text so an admin's own edit to this
 * section is left alone rather than silently stripped.
 */
return new class extends Migration
{
    private const OLD_CLOSING = 'По-малко неща. Повече свобода кога и къде да се погрижиш за зъбите си.';

    public function up(): void
    {
        $block = DB::table('content_blocks')->where('key', 'funnel.why')->first();

        if ($block === null) {
            return;
        }

        $content = json_decode($block->content, true);

        if (($content['closing'] ?? null) !== self::OLD_CLOSING) {
            return;
        }

        unset($content['closing']);

        DB::table('content_blocks')
            ->where('key', 'funnel.why')
            ->update([
                'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Content-only removal with no meaningful prior state to restore
        // to - reverting would just reintroduce the old closing line.
    }
};
