<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FunnelSeeder's default "What Is Miswak" (funnel.intro) copy was
 * rewritten wholesale - new title, a single lead-in paragraph replacing
 * the old explainer, no separate benefits_title heading (see
 * WhatIsMiswakSection.tsx, now rendered conditionally), and an expanded
 * six-item benefits list. The seeder keys its updateOrCreate on an
 * already-seeded identity (content_blocks: key) and so silently no-ops on
 * any environment seeded before this change - including production.
 * Pushes the new content there directly, the same way
 * 2026_09_19_000000_update_funnel_hero_title_default did for the hero.
 * Guarded on the OLD title so an admin's own rewrite of this section is
 * left alone rather than overwritten.
 */
return new class extends Migration
{
    private const OLD_TITLE = 'Какво всъщност е Miswak?';

    private const NEW_CONTENT = [
        'title' => 'Какво прави една пръчица толкова добра в почистването?',
        'paragraphs' => [
            'Miswak не разчита на сложни формули. В самата Salvadora persica естествено се съдържат вещества, които допълват механичното почистване:',
        ],
        'benefits_title' => null,
        'benefits' => [
            [
                'label' => 'Естествени влакна',
                'description' => 'почистват плаката и нежно полират повърхността на зъбите, за по-чист и естествено сияен вид.',
            ],
            ['label' => 'Силициев диоксид', 'description' => 'подпомага механичното почистване и полирането.'],
            ['label' => 'Калций и калий', 'description' => 'естествено срещащи се минерали в структурата на растението.'],
            [
                'label' => 'Естествено съдържащи се флуориди',
                'description' => 'традиционно свързвани с поддържането на добра устна хигиена.',
            ],
            ['label' => 'Етерични масла', 'description' => 'допринасят за свежото усещане след употреба.'],
            [
                'label' => 'Растителни антиоксиданти и биоактивни съединения',
                'description' => 'естествена част от състава на Salvadora persica.',
            ],
        ],
    ];

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

        DB::table('content_blocks')
            ->where('key', 'funnel.intro')
            ->update([
                'content' => json_encode(self::NEW_CONTENT, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Content-only rewrite with no meaningful prior state to restore
        // to - reverting would just reintroduce the old copy.
    }
};
