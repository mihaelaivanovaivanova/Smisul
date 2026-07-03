<?php

namespace App\Http\Requests\Product;

use App\Enums\VariantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductVariantRequest extends FormRequest
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
            'sku' => ['required', 'string', 'max:100', 'unique:product_variants,sku'],
            'name' => ['required', 'string', 'max:255'],
            'pack_size' => ['required', 'integer', 'min:1'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::enum(VariantStatus::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
