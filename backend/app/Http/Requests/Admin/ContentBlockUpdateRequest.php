<?php

namespace App\Http\Requests\Admin;

use App\Models\ContentBlock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentBlockUpdateRequest extends FormRequest
{
    private const ICONS = ['leaf', 'shield', 'heart', 'sparkle', 'truck', 'cart'];

    public function authorize(): bool
    {
        return $this->user()->can('update', ContentBlock::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return match ($this->route('section')) {
            'hero' => [
                'eyebrow' => ['required', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'subtitle' => ['required', 'string', 'max:1000'],
                'cta' => ['required', 'string', 'max:100'],
            ],
            'featured' => [
                'eyebrow' => ['required', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string', 'max:1000'],
                'cta' => ['required', 'string', 'max:100'],
                'product_id' => ['nullable', 'integer', 'exists:products,id'],
            ],
            'benefits' => [
                'title' => ['required', 'string', 'max:255'],
                'lead' => ['required', 'string', 'max:1000'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.icon' => ['required', Rule::in(self::ICONS)],
                'items.*.title' => ['required', 'string', 'max:255'],
                'items.*.text' => ['required', 'string', 'max:500'],
            ],
            'usage' => [
                'title' => ['required', 'string', 'max:255'],
                'steps' => ['required', 'array', 'min:1'],
                'steps.*.title' => ['required', 'string', 'max:255'],
                'steps.*.text' => ['required', 'string', 'max:500'],
            ],
            'trust' => [
                'title' => ['required', 'string', 'max:255'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.icon' => ['required', Rule::in(self::ICONS)],
                'items.*.text' => ['required', 'string', 'max:500'],
            ],
            'delivery' => [
                'title' => ['required', 'string', 'max:255'],
                'icon' => ['required', Rule::in(self::ICONS)],
                'text' => ['required', 'string', 'max:1000'],
            ],
            'bio' => [
                'eyebrow' => ['required', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'text' => ['required', 'string', 'max:1000'],
            ],
            'faq' => [
                'title' => ['required', 'string', 'max:255'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.question' => ['required', 'string', 'max:255'],
                'items.*.answer' => ['required', 'string', 'max:1000'],
            ],
            // Unreachable in practice: the route itself constrains {section}
            // to ContentBlockService::HOMEPAGE_SECTIONS via a where() regex,
            // and the service throws if this were ever bypassed.
            default => [],
        };
    }
}
