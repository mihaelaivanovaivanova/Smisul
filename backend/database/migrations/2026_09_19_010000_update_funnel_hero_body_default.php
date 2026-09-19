<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FunnelSeeder's default hero body changed, but the seeder keys its
 * updateOrCreate on an already-seeded identity (content_blocks: key) and
 * so silently no-ops on any environment seeded before this change -
 * including production. Pushes the new body there directly, the same
 * way 2026_09_19_000000_update_funnel_hero_title_default did for the
 * title. Only the base "funnel.hero" row is touched - an ad-angle
 * variant's own hero override (stored under "funnel.variant.{slug}.hero")
 * is a separate, intentional admin edit and is left alone.
 */
return new class extends Migration
{
    private const OLD_BODY = 'Miswak е естествена пръчица от Salvadora persica за почистване на зъбите, която можеш да използваш без паста и без мивка - след кафе, в офиса, в колата или когато си на път.';

    private const NEW_BODY = 'Miswak е естествена пръчица от Salvadora persica за почистване на зъбите, която можеш да използваш без паста и без мивка - изцяло натурална и биоразградима, със същата сила да гарантира блестящата ти усмивка!';

    public function up(): void
    {
        $block = DB::table('content_blocks')->where('key', 'funnel.hero')->first();

        if ($block === null) {
            return;
        }

        $content = json_decode($block->content, true);

        if (($content['body'] ?? null) !== self::OLD_BODY) {
            return;
        }

        $content['body'] = self::NEW_BODY;

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
        // restore to - reverting would just reintroduce the old body.
    }
};
