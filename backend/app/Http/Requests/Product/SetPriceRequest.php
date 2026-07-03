<?php

namespace App\Http\Requests\Product;

use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'currency' => ['required', Rule::enum(Currency::class)],
            'amount' => ['required', 'numeric', 'min:0'],
            'compare_at_amount' => ['nullable', 'numeric', 'gt:amount'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
