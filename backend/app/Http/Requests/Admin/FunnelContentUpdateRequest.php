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
                'badge' => ['required', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'body' => ['required', 'string', 'max:1000'],
                'highlight' => ['required', 'string', 'max:255'],
                'cta_primary' => ['required', 'string', 'max:100'],
                'cta_secondary' => ['required', 'string', 'max:100'],
                'bullets' => ['required', 'array', 'min:1'],
                'bullets.*' => ['required', 'string', 'max:255'],
            ],
            'dark_band' => [
                'eyebrow' => ['required', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'paragraphs' => ['required', 'array', 'min:1'],
                'paragraphs.*' => ['required', 'string', 'max:1000'],
                'highlight' => ['required', 'string', 'max:255'],
            ],
            'problem' => [
                'eyebrow' => ['required', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'body' => ['required', 'string', 'max:1000'],
                'emphasis' => ['required', 'string', 'max:255'],
                'bullets' => ['required', 'array', 'min:1'],
                'bullets.*' => ['required', 'string', 'max:255'],
                'cta' => ['required', 'string', 'max:100'],
            ],
            'benefits' => [
                'eyebrow' => ['required', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'cards' => ['required', 'array', 'min:1'],
                'cards.*.title' => ['required', 'string', 'max:255'],
                'cards.*.text' => ['required', 'string', 'max:500'],
                'cards.*.emphasis' => ['required', 'string', 'max:255'],
            ],
            'ingredients' => [
                'eyebrow' => ['required', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'cards' => ['required', 'array', 'min:1'],
                'cards.*.title' => ['required', 'string', 'max:255'],
                'cards.*.text' => ['required', 'string', 'max:500'],
                'closing_line' => ['required', 'string', 'max:255'],
            ],
            'ritual' => [
                'eyebrow' => ['required', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'lines' => ['required', 'array', 'min:1'],
                'lines.*' => ['required', 'string', 'max:255'],
                'cta' => ['required', 'string', 'max:100'],
                'steps' => ['required', 'array', 'min:1'],
                'steps.*.title' => ['required', 'string', 'max:255'],
                'steps.*.text' => ['required', 'string', 'max:500'],
            ],
            'how_to' => [
                'eyebrow' => ['required', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'steps' => ['required', 'array', 'min:1'],
                'steps.*.title' => ['required', 'string', 'max:255'],
                'steps.*.text' => ['required', 'string', 'max:500'],
                'note' => ['required', 'string', 'max:500'],
            ],
            'packages_intro' => [
                'eyebrow' => ['required', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'intro' => ['required', 'string', 'max:500'],
            ],
            'labels' => [
                'eyebrow' => ['required', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'lines' => ['required', 'array', 'min:1'],
                'lines.*' => ['required', 'string', 'max:255'],
                'body' => ['required', 'string', 'max:1000'],
                'cta' => ['required', 'string', 'max:100'],
            ],
            'testimonials' => [
                'eyebrow' => ['required', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'quotes' => ['required', 'array', 'min:1'],
                'quotes.*.name' => ['required', 'string', 'max:255'],
                'quotes.*.quote' => ['required', 'string', 'max:1000'],
            ],
            'faq' => [
                'title' => ['required', 'string', 'max:255'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.question' => ['required', 'string', 'max:255'],
                'items.*.answer' => ['required', 'string', 'max:1000'],
            ],
            'final_cta' => [
                'eyebrow' => ['required', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'lines' => ['required', 'array', 'min:1'],
                'lines.*' => ['required', 'string', 'max:255'],
                'body' => ['required', 'string', 'max:1000'],
                'trust_line' => ['required', 'string', 'max:255'],
                'cta' => ['required', 'string', 'max:100'],
            ],
            // Unreachable in practice: the route constrains {section} to
            // FunnelContentService::FUNNEL_SECTIONS via a where() regex, and
            // the service throws if this were ever bypassed.
            default => [],
        };
    }
}
