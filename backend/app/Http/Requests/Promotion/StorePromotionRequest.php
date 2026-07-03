<?php

namespace App\Http\Requests\Promotion;

use App\Enums\PromotionType;
use App\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Promotion::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::enum(PromotionType::class)],
            'value' => [
                'required',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail): void {
                    if ($this->input('type') === PromotionType::Percentage->value && $value > 100) {
                        $fail('A percentage promotion cannot exceed 100%.');
                    }
                },
            ],
            'code' => ['nullable', 'string', 'max:50', 'unique:promotions,code'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }
}
