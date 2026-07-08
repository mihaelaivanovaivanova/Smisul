<?php

namespace App\Services;

use App\Models\ContentBlock;
use InvalidArgumentException;

/**
 * Backs the funnel landing page's editable text sections, the same way
 * ContentBlockService backs the normal homepage's — one row per section
 * (key = "funnel.{section}") in the shared content_blocks table. Kept as
 * its own service rather than folded into ContentBlockService because the
 * two pages' section lists are unrelated and would only complicate a
 * single class's constants/validation for no shared benefit.
 */
class FunnelContentService
{
    public const FUNNEL_SECTIONS = [
        'hero',
        'dark_band',
        'problem',
        'benefits',
        'ingredients',
        'ritual',
        'how_to',
        'packages_intro',
        'labels',
        'testimonials',
        'faq',
        'final_cta',
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $blocks = ContentBlock::query()
            ->where('key', 'like', 'funnel.%')
            ->get()
            ->mapWithKeys(fn (ContentBlock $block) => [substr($block->key, strlen('funnel.')) => $block->content]);

        return collect(self::FUNNEL_SECTIONS)
            ->mapWithKeys(fn (string $section) => [$section => $blocks[$section] ?? []])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function updateSection(string $section, array $content): array
    {
        if (! in_array($section, self::FUNNEL_SECTIONS, true)) {
            throw new InvalidArgumentException("Unknown funnel section [{$section}].");
        }

        $block = ContentBlock::query()->updateOrCreate(['key' => "funnel.{$section}"], ['content' => $content]);

        return $block->content;
    }
}
