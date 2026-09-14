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
 *
 * A funnel "variant" (a different ad angle — see FunnelVariant) overrides
 * individual sections under "funnel.variant.{slug}.{section}" keys and
 * falls back to the base "funnel.{section}" row for every section it
 * doesn't customize. This means a new angle only has to override the
 * sections that actually change (hero, intro, why, ...) and keeps sharing
 * the base FAQ/trust content unless explicitly told otherwise.
 */
class FunnelContentService
{
    public const FUNNEL_SECTIONS = [
        'hero',
        'intro',
        'why',
        'features',
        'comparison',
        'history',
        'natural_eco',
        'science',
        'awareness',
        'positioning',
        'final_cta',
        'faq',
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(?string $variantSlug = null): array
    {
        $base = $this->sectionsUnder('funnel.');
        $overrides = $variantSlug !== null ? $this->sectionsUnder("funnel.variant.{$variantSlug}.") : [];

        return collect(self::FUNNEL_SECTIONS)
            ->mapWithKeys(fn (string $section) => [$section => $overrides[$section] ?? $base[$section] ?? []])
            ->all();
    }

    /**
     * Which sections a variant has actually overridden — drives the admin
     * UI's "customized for this angle" vs. "inherited from base" badge.
     *
     * @return list<string>
     */
    public function overriddenSections(string $variantSlug): array
    {
        return array_values(array_intersect(self::FUNNEL_SECTIONS, array_keys($this->sectionsUnder("funnel.variant.{$variantSlug}."))));
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function updateSection(string $section, array $content, ?string $variantSlug = null): array
    {
        $this->assertKnownSection($section);

        $key = $variantSlug !== null ? "funnel.variant.{$variantSlug}.{$section}" : "funnel.{$section}";
        $block = ContentBlock::query()->updateOrCreate(['key' => $key], ['content' => $content]);

        return $block->content;
    }

    /**
     * Removes a variant's override for one section so it falls back to the
     * base content again. No-op if the section was never overridden.
     */
    public function resetSection(string $section, string $variantSlug): void
    {
        $this->assertKnownSection($section);

        ContentBlock::query()->where('key', "funnel.variant.{$variantSlug}.{$section}")->delete();
    }

    /**
     * Deletes every section override belonging to a variant — called when
     * the variant itself is deleted, so orphaned content_blocks rows don't
     * pile up under a slug nothing references any more.
     */
    public function deleteVariantContent(string $variantSlug): void
    {
        ContentBlock::query()->where('key', 'like', "funnel.variant.{$variantSlug}.%")->delete();
    }

    private function assertKnownSection(string $section): void
    {
        if (! in_array($section, self::FUNNEL_SECTIONS, true)) {
            throw new InvalidArgumentException("Unknown funnel section [{$section}].");
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sectionsUnder(string $prefix): array
    {
        return ContentBlock::query()
            ->where('key', 'like', "{$prefix}%")
            // Excludes a variant's own "funnel.variant.{slug}.*" rows from
            // matching the base "funnel." prefix scan (both start with
            // "funnel."), since the base scan must return only the twelve
            // real sections, not every variant's overrides too.
            ->when($prefix === 'funnel.', fn ($query) => $query->where('key', 'not like', 'funnel.variant.%'))
            ->get()
            ->mapWithKeys(fn (ContentBlock $block) => [substr($block->key, strlen($prefix)) => $block->content])
            ->all();
    }
}
