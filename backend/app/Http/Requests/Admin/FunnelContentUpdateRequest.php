<?php

namespace App\Http\Requests\Admin;

use App\Models\FunnelConfig;
use Illuminate\Foundation\Http\FormRequest;

class FunnelContentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', FunnelConfig::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return match ($this->route('section')) {
            'hero' => [
                // Optional kicker line above the H1 — nullable so content
                // saved before the field existed stays valid.
                'eyebrow' => ['nullable', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'body' => ['required', 'string', 'max:1000'],
                'cta_primary' => ['required', 'string', 'max:100'],
                'cta_secondary' => ['required', 'string', 'max:100'],
                'trust_items' => ['required', 'array', 'min:1'],
                'trust_items.*.icon' => ['required', 'string', 'max:50'],
                'trust_items.*.label' => ['required', 'string', 'max:100'],
            ],
            'intro' => [
                'title' => ['required', 'string', 'max:255'],
                'paragraphs' => ['required', 'array', 'min:1'],
                'paragraphs.*' => ['required', 'string', 'max:1000'],
            ],
            'why' => [
                'title' => ['required', 'string', 'max:255'],
                'cards' => ['required', 'array', 'min:1'],
                'cards.*.icon' => ['required', 'string', 'max:50'],
                'cards.*.title' => ['required', 'string', 'max:255'],
                'cards.*.text' => ['required', 'string', 'max:500'],
                'closing' => ['required', 'string', 'max:500'],
            ],
            'history' => [
                'title' => ['required', 'string', 'max:255'],
                'subtitle' => ['required', 'string', 'max:255'],
                'body' => ['required', 'string', 'max:500'],
            ],
            'natural_eco' => [
                'eyebrow' => ['required', 'string', 'max:100'],
                'title' => ['required', 'string', 'max:255'],
                'paragraphs' => ['required', 'array', 'min:1'],
                'paragraphs.*' => ['required', 'string', 'max:1000'],
                'brand_statement' => ['required', 'string', 'max:500'],
            ],
            'features' => [
                'title' => ['required', 'string', 'max:255'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.icon' => ['required', 'string', 'max:100'],
                'items.*.label' => ['required', 'string', 'max:100'],
            ],
            'comparison' => [
                'title' => ['required', 'string', 'max:255'],
                'miswak_label' => ['required', 'string', 'max:100'],
                'brush_label' => ['required', 'string', 'max:100'],
                // Each column's value is free text, not a fixed yes/no —
                // "✓" / "✕" / "△" render as marks, anything else renders
                // as plain text (e.g. "Допълва", "обикновено ✕").
                'rows' => ['required', 'array', 'min:1'],
                'rows.*.label' => ['required', 'string', 'max:255'],
                'rows.*.miswak_value' => ['required', 'string', 'max:50'],
                'rows.*.brush_value' => ['required', 'string', 'max:50'],
                'closing' => ['required', 'string', 'max:255'],
            ],
            'science' => [
                'eyebrow' => ['required', 'string', 'max:100'],
                'title' => ['required', 'string', 'max:255'],
                'intro' => ['required', 'string', 'max:1000'],
                'cards' => ['required', 'array', 'min:1'],
                'cards.*.title' => ['required', 'string', 'max:255'],
                'cards.*.body' => ['required', 'string', 'max:1000'],
                // Cited-claim links — nullable/empty string means "no link
                // for this card", not a broken/missing URL.
                'cards.*.source_url' => ['nullable', 'string', 'max:2048', 'url'],
                'cards.*.source_label' => ['nullable', 'string', 'max:100'],
                'callout.stat' => ['required', 'string', 'max:255'],
                'callout.body' => ['required', 'string', 'max:1000'],
                'safety.title' => ['required', 'string', 'max:255'],
                'safety.body' => ['required', 'string', 'max:500'],
            ],
            'awareness' => [
                'title' => ['required', 'string', 'max:255'],
                'subtitle' => ['required', 'string', 'max:255'],
                'body' => ['required', 'string', 'max:1000'],
            ],
            'positioning' => [
                'title' => ['required', 'string', 'max:255'],
                'body' => ['required', 'string', 'max:500'],
            ],
            'final_cta' => [
                'title' => ['required', 'string', 'max:255'],
                'paragraphs' => ['required', 'array', 'min:1'],
                'paragraphs.*' => ['required', 'string', 'max:1000'],
                'cta' => ['required', 'string', 'max:100'],
                'trust_items' => ['required', 'array', 'min:1'],
                'trust_items.*.icon' => ['required', 'string', 'max:50'],
                'trust_items.*.label' => ['required', 'string', 'max:100'],
            ],
            'faq' => [
                'title' => ['required', 'string', 'max:255'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.question' => ['required', 'string', 'max:255'],
                'items.*.answer' => ['required', 'string', 'max:2000'],
                'items.*.attachment_url' => ['nullable', 'string', 'max:2048'],
                'items.*.attachment_label' => ['nullable', 'string', 'max:100'],
            ],
            // Unreachable in practice: the route constrains {section} to
            // FunnelContentService::FUNNEL_SECTIONS via a where() regex, and
            // the service throws if this were ever bypassed.
            default => [],
        };
    }
}
